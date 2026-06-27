<?php

namespace App\Traits;

use App\Models\Activity;
use Carbon\Carbon;

/**
 * TimeEnforcementHelper
 * 
 * Provides utility methods for time-based activity enforcement.
 * Determines if activities/sessions are scheduled, active, or closed based on their time windows.
 */
trait TimeEnforcementHelper
{
    /**
     * Determine the time status of an activity.
     *
     * @param Carbon|null $startTime  Activity start time (or null if no start time)
     * @param Carbon|null $endTime    Activity end time (due date or session end)
     * @return array {status: 'scheduled'|'active'|'closed', time_remaining_seconds: int, can_attempt: bool}
     */
    protected function getActivityTimeStatus(?Carbon $startTime, ?Carbon $endTime): array
    {
        $now = now();
        
        // If no time restrictions, activity is always available
        if (!$startTime && !$endTime) {
            return [
                'status' => 'active',
                'time_remaining_seconds' => null,
                'can_attempt' => true,
            ];
        }
        
        // Check if activity hasn't started yet
        if ($startTime && $now < $startTime) {
            return [
                'status' => 'scheduled',
                'time_remaining_seconds' => (int) $startTime->diffInSeconds($now, false),
                'can_attempt' => false,
                'reason' => 'not_started',
            ];
        }
        
        // Check if activity has ended
        if ($endTime && $now > $endTime) {
            return [
                'status' => 'closed',
                'time_remaining_seconds' => 0,
                'can_attempt' => false,
                'reason' => 'closed',
            ];
        }
        
        // Activity is currently active
        $secondsUntilEnd = $endTime ? (int) $endTime->diffInSeconds($now, false) : null;
        
        return [
            'status' => 'active',
            'time_remaining_seconds' => $secondsUntilEnd,
            'can_attempt' => true,
        ];
    }
    
    /**
     * Resolve a quiz activity's effective timing window from its settings.
     *
     * Authoring (QuizCreator) stores openDate / closeDate / timeLimit in
     * activity.settings; this is the single place that reads them (with legacy
     * fallbacks) so start, submit, and the expiry command all agree. All strings
     * are parsed with Carbon, i.e. in the app timezone (single-timezone contract).
     *
     * @return array{open: ?Carbon, close: ?Carbon, timeLimitMinutes: ?int}
     */
    protected function resolveQuizWindow(Activity $activity): array
    {
        $settings = $activity->settings ?? [];

        $parse = static function ($value): ?Carbon {
            if (empty($value)) {
                return null;
            }
            try {
                return Carbon::parse($value);
            } catch (\Throwable $e) {
                return null;
            }
        };

        $open  = $parse($settings['openDate'] ?? $settings['start_time'] ?? null);
        $close = $parse($settings['closeDate'] ?? null);
        if (!$close && $activity->due_date) {
            $close = $activity->due_date->copy()->endOfDay();
        }

        $limit = $settings['timeLimit'] ?? null;
        $limit = is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;

        return ['open' => $open, 'close' => $close, 'timeLimitMinutes' => $limit];
    }

    /**
     * The hard deadline for an in-progress attempt: the sooner of
     * (started_at + time limit) and the quiz close time. Null = no deadline.
     *
     * @param array{open: ?Carbon, close: ?Carbon, timeLimitMinutes: ?int} $window
     */
    protected function quizAttemptDeadline(?Carbon $startedAt, array $window): ?Carbon
    {
        $byLimit = ($startedAt && !empty($window['timeLimitMinutes']))
            ? $startedAt->copy()->addMinutes($window['timeLimitMinutes'])
            : null;
        $close = $window['close'] ?? null;

        if ($byLimit && $close) {
            return $byLimit->lt($close) ? $byLimit : $close;
        }

        return $byLimit ?: $close;
    }

    /**
     * Read an activity's per-attempt time limit (minutes) from settings.timeLimit.
     * Shared convention across quiz, practical and (text-only) assignment.
     * Returns null when unset / non-positive (= untimed).
     */
    protected function resolveActivityTimeLimit(Activity $activity): ?int
    {
        $limit = ($activity->settings ?? [])['timeLimit'] ?? null;
        return is_numeric($limit) && (int) $limit > 0 ? (int) $limit : null;
    }

    /**
     * Hard deadline for a timed attempt: started_at + limit minutes.
     * Null when either input is missing (= no deadline).
     */
    protected function deadlineFromStart(?Carbon $startedAt, ?int $minutes): ?Carbon
    {
        return ($startedAt && $minutes) ? $startedAt->copy()->addMinutes($minutes) : null;
    }

    /**
     * Standard client payload for a deadline so every timed surface agrees:
     * the client seeds its countdown from `time_limit_seconds` (remaining) and
     * `server_time`, never its own clock. Mirrors QuizController::startAttempt.
     */
    protected function deadlinePayload(?Carbon $deadline): array
    {
        return [
            'expires_at'         => $deadline?->toIso8601String(),
            'time_limit_seconds' => $deadline ? max(0, (int) now()->diffInSeconds($deadline, false)) : null,
            'server_time'        => now()->toIso8601String(),
        ];
    }

    /**
     * Whether a submission at `now` is past the deadline (with a small grace).
     */
    protected function isPastDeadline(?Carbon $deadline, int $graceSeconds = 30): bool
    {
        return $deadline !== null && now()->gt($deadline->copy()->addSeconds($graceSeconds));
    }

    /**
     * Get time status for a session.
     * Sessions have scheduled_at (when session should start) and duration (in minutes).
     * Calculated end time is scheduled_at + duration.
     *
     * @param Carbon $scheduledAt  When the session is scheduled to start
     * @param int|null $durationMinutes Duration in minutes (null if not set)
     * @return array {status, time_remaining_seconds, can_join}
     */
    protected function getSessionTimeStatus(Carbon $scheduledAt, ?int $durationMinutes): array
    {
        $now = now();
        
        // Session hasn't started yet
        if ($now < $scheduledAt) {
            return [
                'status' => 'scheduled',
                'time_remaining_seconds' => (int) $scheduledAt->diffInSeconds($now, false),
                'can_join' => false,
                'reason' => 'not_started',
            ];
        }
        
        // Calculate session end time
        $endTime = $scheduledAt->copy()->addMinutes($durationMinutes ?? 120);
        
        // Session has ended
        if ($now > $endTime) {
            return [
                'status' => 'closed',
                'time_remaining_seconds' => 0,
                'can_join' => false,
                'reason' => 'closed',
            ];
        }
        
        // Session is currently active
        $secondsUntilEnd = (int) $endTime->diffInSeconds($now, false);
        
        return [
            'status' => 'active',
            'time_remaining_seconds' => $secondsUntilEnd,
            'can_join' => true,
        ];
    }
}
