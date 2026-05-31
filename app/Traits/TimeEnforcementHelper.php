<?php

namespace App\Traits;

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
