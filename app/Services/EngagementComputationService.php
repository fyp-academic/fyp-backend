<?php

namespace App\Services;

use Illuminate\Support\Str;
use App\Models\BehavioralSignal;
use App\Models\CognitiveSignal;
use App\Models\EmotionalSignal;
use App\Models\EngagementScore;
use App\Models\LearnerActivityEvent;
use App\Models\LearnerLoginSession;
use App\Models\LearningStreak;
use App\Models\MaterialInteraction;
use App\Models\AssignmentSubmission;
use App\Models\Session;
use App\Models\SessionParticipant;
use App\Models\SessionPoll;
use App\Models\Notification;
use Carbon\Carbon;

class EngagementComputationService
{
    // ── Formula weights ───────────────────────────────────────────────────
    private const W_LOGIN   = 0.15;
    private const W_CONTENT = 0.25;
    private const W_ASSESS  = 0.20;
    private const W_FORUM   = 0.15;
    private const W_PACING  = 0.15;
    private const W_LIVE    = 0.10;

    // ── Thresholds ────────────────────────────────────────────────────────
    // Bounce + failure thresholds now live in config/engagement.php so they are
    // tunable without code changes. IDEAL_WEEK_DAYS is a fixed normalisation base.
    private const IDEAL_WEEK_DAYS = 7;

    private function failurePct(): float
    {
        return (float) config('engagement.failure_pct', 50);
    }

    // ─────────────────────────────────────────────────────────────────────
    // MAIN ENTRY POINT
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Compute and persist the engagement score for a learner in a course for a given week.
     * Safe to call multiple times — uses updateOrCreate.
     */
    public function computeForWeek(string $learnerId, string $courseId, int $weekNumber): EngagementScore
    {
        // Each component returns a 0-100 score, or null when the signal is
        // "not applicable" for this learner-week (no forum in the course, no
        // live session scheduled). Null signals are excluded from the score
        // instead of being filled with a misleading default.
        $L = $this->computeLoginConsistency($learnerId, $courseId, $weekNumber);
        $C = $this->computeContentCompletion($learnerId, $courseId, $weekNumber);
        $A = $this->computeAssessmentActivity($learnerId, $courseId, $weekNumber);
        $F = $this->computeForumParticipation($learnerId, $courseId, $weekNumber);
        $P = $this->computePacingScore($learnerId, $courseId, $weekNumber);
        $S = $this->computeLiveSessionScore($learnerId, $courseId, $weekNumber);

        // Renormalise weights over only the measured signals.
        [$finalScore, $meta] = EngagementScore::weightedScore(compact('L', 'C', 'A', 'F', 'P', 'S'));

        $prev = EngagementScore::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('week_number', $weekNumber - 1)
            ->value('engagement_score');

        $delta = $prev !== null ? round($finalScore - $prev, 2) : null;

        $record = EngagementScore::updateOrCreate(
            ['learner_id' => $learnerId, 'course_id' => $courseId, 'week_number' => $weekNumber],
            [
                'id'                        => Str::uuid()->toString(),
                // Absent signals store 0 in their (non-null) column but are flagged
                // as 'absent' in component_breakdown so the UI shows N/A, not 0.
                'login_consistency_score'   => $L ?? 0,
                'content_completion_score'  => $C ?? 0,
                'assessment_activity_score' => $A ?? 0,
                'forum_participation_score' => $F ?? 0,
                'pacing_score'              => $P ?? 0,
                'live_session_score'        => $S ?? 0,
                'engagement_score'          => $finalScore,
                'previous_week_score'       => $prev,
                'score_delta'               => $delta,
                'component_breakdown'       => $meta,
                'computed_at'               => now(),
            ]
        );

        // Propagate selected derived fields into signal tables so analytics views stay current
        $this->propagateToBehavioralSignal($learnerId, $courseId, $weekNumber);
        $this->propagateToEmotionalSignal($learnerId, $courseId, $weekNumber);

        return $record;
    }

    // ─────────────────────────────────────────────────────────────────────
    // L — Login Consistency (0-100)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * E = 0.50 × (consecutive_days / 7)
     *   + 0.30 × (1 - bounce_rate)
     *   + 0.20 × (1 - inactivity_gap / 7)
     */
    private function computeLoginConsistency(string $learnerId, string $courseId, int $weekNumber): float
    {
        [$start, $end] = $this->weekBounds($courseId, $weekNumber);

        $sessions = LearnerLoginSession::where('user_id', $learnerId)
            ->whereBetween('started_at', [$start, $end])
            ->get(['started_at', 'duration_seconds', 'is_bounce']);

        if ($sessions->isEmpty()) {
            return 0.0;
        }

        // Consecutive active days from streak table
        $streak = LearningStreak::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->first();
        $consecutiveDays = $streak ? min($streak->current_streak_days, self::IDEAL_WEEK_DAYS) : 0;
        $streakRatio     = $consecutiveDays / self::IDEAL_WEEK_DAYS;

        // Bounce rate
        $totalSessions = $sessions->count();
        $bounceSessions = $sessions->where('is_bounce', true)->count();
        $bounceRate = $totalSessions > 0 ? $bounceSessions / $totalSessions : 1;

        // Average inactivity gap
        $sortedDates = $sessions->pluck('started_at')->sort()->values();
        $gaps = [];
        for ($i = 1; $i < $sortedDates->count(); $i++) {
            $gaps[] = $sortedDates[$i]->diffInDays($sortedDates[$i - 1]);
        }
        $avgGap = count($gaps) > 0 ? array_sum($gaps) / count($gaps) : self::IDEAL_WEEK_DAYS;
        $inactivityScore = max(0, 1 - ($avgGap / self::IDEAL_WEEK_DAYS));

        $L = (0.50 * $streakRatio + 0.30 * (1 - $bounceRate) + 0.20 * $inactivityScore) * 100;

        return $this->clamp($L);
    }

    // ─────────────────────────────────────────────────────────────────────
    // C — Content Completion (0-100)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * E = 0.40 × completion_rate
     *   + 0.35 × avg_video_watch_percent
     *   + 0.25 × avg_material_depth
     */
    private function computeContentCompletion(string $learnerId, string $courseId, int $weekNumber): float
    {
        $bSig = BehavioralSignal::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('week_number', $weekNumber)
            ->first();

        $completionRate = $bSig ? ($bSig->content_completion_rate / 100) : 0;

        [$start, $end] = $this->weekBounds($courseId, $weekNumber);
        $interactions = MaterialInteraction::where('student_id', $learnerId)
            ->where('course_id', $courseId)
            ->whereBetween('last_interaction_at', [$start, $end])
            ->get(['video_watch_percent', 'completion_percent']);

        $avgVideo = $interactions->whereNotNull('video_watch_percent')->avg('video_watch_percent') ?? 0;
        $avgDepth = $interactions->avg('completion_percent') ?? 0;

        $C = (0.40 * $completionRate + 0.35 * ($avgVideo / 100) + 0.25 * ($avgDepth / 100)) * 100;

        return $this->clamp($C);
    }

    // ─────────────────────────────────────────────────────────────────────
    // A — Assessment Activity (0-100)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * E = 0.40 × attempt_rate
     *   + 0.40 × normalize(quiz_learning_delta)
     *   + 0.20 × (1 - skip_rate)
     */
    private function computeAssessmentActivity(string $learnerId, string $courseId, int $weekNumber): float
    {
        $bSig = BehavioralSignal::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('week_number', $weekNumber)
            ->first();

        $cSig = CognitiveSignal::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('week_number', $weekNumber)
            ->first();

        $attemptRate  = ($bSig && $bSig->quiz_available_count > 0)
            ? min($bSig->quiz_attempt_count / $bSig->quiz_available_count, 1)
            : 0;

        // Learning delta: improvement between first and final quiz attempt (-100 to +100 → normalize 0-1)
        $learningDelta = $cSig ? (($cSig->quiz_learning_delta ?? 0) + 100) / 200 : 0.5;

        $skipRate = $cSig ? ($cSig->quiz_question_skip_rate / 100) : 0;

        $A = (0.40 * $attemptRate + 0.40 * $learningDelta + 0.20 * (1 - $skipRate)) * 100;

        return $this->clamp($A);
    }

    // ─────────────────────────────────────────────────────────────────────
    // F — Forum Participation (0-100)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * E = 0.50 × quality-weighted post ratio
     *   + 0.30 × peer_response_rate
     *   + 0.20 × normalize(discussion_depth_score)
     */
    private function computeForumParticipation(string $learnerId, string $courseId, int $weekNumber): ?float
    {
        // Not applicable when the course has no forum at all — return null so the
        // signal is excluded from the score rather than penalised with a default.
        if (!\App\Models\ForumDiscussion::where('course_id', $courseId)->exists()) {
            return null;
        }

        $bSig = BehavioralSignal::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('week_number', $weekNumber)
            ->first();

        $cSig = CognitiveSignal::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('week_number', $weekNumber)
            ->first();

        // Quality-weighted post ratio: (actual_posts × avg_quality) / required_posts
        $required = $bSig ? max($bSig->forum_posts_required, 1) : 1;
        $posted   = $bSig ? ($bSig->forum_post_count ?? 0) : 0;

        [$start, $end] = $this->weekBounds($courseId, $weekNumber);
        // Use real AI quality scores when available. When posts exist but aren't
        // scored yet we count the participation at face value (quality = 1.0)
        // rather than fabricating a 0.5 penalty.
        $avgQuality = \App\Models\ForumPost::where('user_id', $learnerId)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('quality_score')
            ->avg('quality_score') ?? 1.0;

        $qualityRatio = min(($posted * $avgQuality) / $required, 1);

        $peerRate = $cSig ? ($cSig->peer_response_rate / 100) : 0;

        // discussion_depth_score normalised (assume 0-10 scale → 0-1)
        $depthNorm = $cSig ? min(($cSig->discussion_depth_score ?? 0) / 10, 1) : 0;

        $F = (0.50 * $qualityRatio + 0.30 * $peerRate + 0.20 * $depthNorm) * 100;

        return $this->clamp($F);
    }

    // ─────────────────────────────────────────────────────────────────────
    // P — Pacing / Schedule Adherence (0-100)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * E = 0.50 × on-time activity ratio (based on submission_timing)
     *   + 0.50 × content progression rate this week
     */
    private function computePacingScore(string $learnerId, string $courseId, int $weekNumber): float
    {
        $bSig = BehavioralSignal::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('week_number', $weekNumber)
            ->first();

        $timingScore = match ($bSig->submission_timing ?? 'missing') {
            'early'   => 1.0,
            'on_time' => 0.8,
            'late'    => 0.4,
            default   => 0.0,
        };

        $progressionRate = $bSig ? ($bSig->content_completion_rate / 100) : 0;

        $P = (0.50 * $timingScore + 0.50 * $progressionRate) * 100;

        return $this->clamp($P);
    }

    // ─────────────────────────────────────────────────────────────────────
    // S — Live Session (0-100)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * E = 0.40 × attendance rate
     *   + 0.30 × participation duration ratio
     *   + 0.20 × poll response rate
     *   + 0.10 × active participation (mic/camera/hands/chat)
     */
    private function computeLiveSessionScore(string $learnerId, string $courseId, int $weekNumber): ?float
    {
        [$start, $end] = $this->weekBounds($courseId, $weekNumber);

        $scheduledSessions = Session::where('course_id', $courseId)
            ->whereBetween('scheduled_at', [$start, $end])
            ->pluck('id');

        if ($scheduledSessions->isEmpty()) {
            // No session scheduled this week — not applicable. Return null so the
            // signal is excluded (previously returned a misleading neutral 100).
            return null;
        }

        $participants = SessionParticipant::where('user_id', $learnerId)
            ->whereIn('session_id', $scheduledSessions)
            ->get();

        $attended = $participants->count();
        $total    = $scheduledSessions->count();
        $attendanceRate = $total > 0 ? $attended / $total : 0;

        // Average participation duration vs session duration
        $sessions = Session::whereIn('id', $scheduledSessions)->get(['id', 'duration']);
        $durRatios = [];
        foreach ($participants as $p) {
            $session = $sessions->firstWhere('id', $p->session_id);
            $sessionDurSec = ($session?->duration ?? 60) * 60;
            $durRatios[] = $sessionDurSec > 0
                ? min($p->participation_duration_seconds / $sessionDurSec, 1)
                : 0;
        }
        $avgDurRatio = count($durRatios) > 0 ? array_sum($durRatios) / count($durRatios) : 0;

        // Poll response rate
        $totalPolls = SessionPoll::whereIn('session_id', $scheduledSessions)->count();
        $respondedPolls = $participants->sum('poll_responses_count');
        $pollRate = $totalPolls > 0 ? min($respondedPolls / $totalPolls, 1) : 1;

        // Active participation flag (mic OR camera OR hand raised OR chat)
        $activeCount = $participants->filter(fn($p) =>
            $p->mic_active || $p->camera_active || $p->hands_raised > 0 || $p->chat_messages > 0
        )->count();
        $activeRate = $attended > 0 ? $activeCount / $attended : 0;

        $S = (0.40 * $attendanceRate + 0.30 * $avgDurRatio + 0.20 * $pollRate + 0.10 * $activeRate) * 100;

        return $this->clamp($S);
    }

    // ─────────────────────────────────────────────────────────────────────
    // PROPAGATION — Write derived fields back to signal tables
    // ─────────────────────────────────────────────────────────────────────

    private function propagateToBehavioralSignal(string $learnerId, string $courseId, int $weekNumber): void
    {
        [$start, $end] = $this->weekBounds($courseId, $weekNumber);

        $sessions = LearnerLoginSession::where('user_id', $learnerId)
            ->whereBetween('started_at', [$start, $end])
            ->get(['started_at', 'is_bounce', 'hour_of_day', 'device_type']);

        if ($sessions->isEmpty()) {
            return;
        }

        $streak = LearningStreak::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->first();

        $sortedDates = $sessions->pluck('started_at')->sort()->values();
        $gaps = [];
        for ($i = 1; $i < $sortedDates->count(); $i++) {
            $gaps[] = $sortedDates[$i]->diffInDays($sortedDates[$i - 1]);
        }
        $avgGap = count($gaps) > 0 ? round(array_sum($gaps) / count($gaps), 2) : 0;

        $peakHour = $sessions->groupBy('hour_of_day')
            ->map->count()
            ->sortDesc()
            ->keys()
            ->first();

        $primaryDevice = $sessions->groupBy('device_type')
            ->map->count()
            ->sortDesc()
            ->keys()
            ->first();

        $matInteractions = MaterialInteraction::where('student_id', $learnerId)
            ->where('course_id', $courseId)
            ->whereBetween('last_interaction_at', [$start, $end])
            ->get(['video_watch_percent', 'open_count']);

        $avgVideoWatch = $matInteractions->whereNotNull('video_watch_percent')->avg('video_watch_percent');

        BehavioralSignal::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('week_number', $weekNumber)
            ->update([
                'consecutive_active_days' => $streak?->current_streak_days ?? 0,
                'avg_inactivity_gap_days' => $avgGap,
                'bounce_session_count'    => $sessions->where('is_bounce', true)->count(),
                'peak_hour_of_day'        => $peakHour,
                'device_type_primary'     => $primaryDevice,
                'material_open_count'     => (int) $matInteractions->sum('open_count'),
                'avg_video_watch_percent' => $avgVideoWatch ? round($avgVideoWatch, 2) : null,
            ]);
    }

    private function propagateToEmotionalSignal(string $learnerId, string $courseId, int $weekNumber): void
    {
        [$start, $end] = $this->weekBounds($courseId, $weekNumber);

        // Notification response rate: % of notifications read within 24h of delivery
        $delivered = Notification::where('user_id', $learnerId)
            ->whereBetween('sent_at', [$start, $end])
            ->whereIn('status', ['delivered', 'read'])
            ->get(['read_at', 'sent_at']);

        $notifRate = 0;
        if ($delivered->count() > 0) {
            $readWithin24 = $delivered->filter(fn($n) =>
                $n->read_at && $n->sent_at && $n->sent_at->diffInHours($n->read_at) <= 24
            )->count();
            $notifRate = round($readWithin24 / $delivered->count() * 100, 2);
        }

        // Frustration index: low-score quiz attempts followed by rapid retries
        $frustrationScore = $this->computeFrustrationIndex($learnerId, $courseId, $start, $end);

        // Post-failure inactivity: hours inactive after a quiz score below threshold
        $postFailureHours = $this->computePostFailureInactivity($learnerId, $courseId, $start, $end);

        EmotionalSignal::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('week_number', $weekNumber)
            ->update([
                'notification_response_rate'   => $notifRate,
                'frustration_index'            => $frustrationScore,
                'post_failure_inactivity_hours'=> $postFailureHours,
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FRUSTRATION INDEX (0-100)
    // ─────────────────────────────────────────────────────────────────────

    private function computeFrustrationIndex(
        string $learnerId, string $courseId, Carbon $start, Carbon $end
    ): float {
        $attempts = \App\Models\QuizAttempt::where('student_id', $learnerId)
            ->where('course_id', $courseId)
            ->whereBetween('submitted_at', [$start, $end])
            ->orderBy('submitted_at')
            ->get(['score', 'max_score', 'submitted_at']);

        if ($attempts->isEmpty()) {
            return 0.0;
        }

        $frustrationEvents = 0;
        foreach ($attempts as $attempt) {
            if ($attempt->max_score > 0) {
                $pct = ($attempt->score / $attempt->max_score) * 100;
                if ($pct < $this->failurePct()) {
                    $frustrationEvents++;
                }
            }
        }

        // Normalise: >3 failure attempts in a week = max frustration
        return $this->clamp(($frustrationEvents / 3) * 100);
    }

    // ─────────────────────────────────────────────────────────────────────
    // POST-FAILURE INACTIVITY (hours)
    // ─────────────────────────────────────────────────────────────────────

    private function computePostFailureInactivity(
        string $learnerId, string $courseId, Carbon $start, Carbon $end
    ): ?float {
        $failedAttempt = \App\Models\QuizAttempt::where('student_id', $learnerId)
            ->where('course_id', $courseId)
            ->whereBetween('submitted_at', [$start, $end])
            ->get(['score', 'max_score', 'submitted_at'])
            ->filter(fn($a) => $a->max_score > 0 && ($a->score / $a->max_score * 100) < $this->failurePct())
            ->sortBy('submitted_at')
            ->first();

        if (!$failedAttempt) {
            return null;
        }

        $nextEvent = LearnerActivityEvent::where('user_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('occurred_at', '>', $failedAttempt->submitted_at)
            ->orderBy('occurred_at')
            ->first();

        if (!$nextEvent) {
            return round($failedAttempt->submitted_at->diffInHours($end), 1);
        }

        return round($failedAttempt->submitted_at->diffInHours($nextEvent->occurred_at), 1);
    }

    // ─────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function clamp(float $value, float $min = 0.0, float $max = 100.0): float
    {
        return round(max($min, min($max, $value)), 2);
    }

    /**
     * Returns the [start, end] Carbon timestamps for a week number within a course.
     * Week 1 = course created_at. Weeks are 7-day windows.
     */
    private function weekBounds(string $courseId, int $weekNumber): array
    {
        $course = \App\Models\Course::find($courseId);
        $base   = $course ? Carbon::parse($course->created_at) : Carbon::now()->startOfYear();

        $start = $base->copy()->addWeeks($weekNumber - 1)->startOfDay();
        $end   = $start->copy()->addDays(6)->endOfDay();

        return [$start, $end];
    }

    /**
     * The current week number for a course (Week 1 = course created_at).
     */
    public function currentWeek(string $courseId): int
    {
        $course = \App\Models\Course::find($courseId);
        $base   = $course ? Carbon::parse($course->created_at) : Carbon::now()->startOfYear();

        return max(1, (int) floor($base->diffInWeeks(now())) + 1);
    }

    /**
     * Real active time-on-task for a learner in a course, derived from measured
     * telemetry — NOT estimated. Sums heartbeat `active_seconds` events and
     * material interaction durations, grouped per resource.
     *
     * @return array{total_seconds: int, by_resource: array<int, array{resource_type: ?string, resource_id: ?string, seconds: int}>}
     */
    public function timeOnTask(string $learnerId, string $courseId, ?int $sinceDays = 30): array
    {
        $since = $sinceDays ? now()->subDays($sinceDays) : Carbon::create(2000);

        // Heartbeat events carry accumulated active seconds in `value`.
        $heartbeats = LearnerActivityEvent::where('user_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('event_type', 'heartbeat')
            ->where('occurred_at', '>=', $since)
            ->get(['resource_type', 'resource_id', 'value']);

        $byResource = [];
        foreach ($heartbeats as $hb) {
            $key = ($hb->resource_type ?? 'page') . ':' . ($hb->resource_id ?? '');
            if (!isset($byResource[$key])) {
                $byResource[$key] = [
                    'resource_type' => $hb->resource_type,
                    'resource_id'   => $hb->resource_id,
                    'seconds'       => 0,
                ];
            }
            $byResource[$key]['seconds'] += (int) round($hb->value ?? 0);
        }

        // Material interactions track their own accumulated duration.
        $materials = MaterialInteraction::where('student_id', $learnerId)
            ->where('course_id', $courseId)
            ->where('last_interaction_at', '>=', $since)
            ->get(['material_id', 'total_duration_seconds']);

        foreach ($materials as $m) {
            $key = 'material:' . $m->material_id;
            if (!isset($byResource[$key])) {
                $byResource[$key] = [
                    'resource_type' => 'material',
                    'resource_id'   => $m->material_id,
                    'seconds'       => 0,
                ];
            }
            $byResource[$key]['seconds'] += (int) $m->total_duration_seconds;
        }

        $total = array_sum(array_column($byResource, 'seconds'));

        return [
            'total_seconds' => $total,
            'by_resource'   => array_values($byResource),
        ];
    }

    /**
     * Defensively close login sessions left open (no ended_at) beyond the
     * configured staleness window — abandoned tabs that never sent a
     * close/beacon. Caps duration at the last heartbeat where possible.
     *
     * @return int number of sessions closed
     */
    public function closeStaleSessions(): int
    {
        $minutes = (int) config('engagement.stale_session_minutes', 30);
        $cutoff  = now()->subMinutes($minutes);

        $stale = LearnerLoginSession::whereNull('ended_at')
            ->where('started_at', '<', $cutoff)
            ->get();

        $closed = 0;
        foreach ($stale as $session) {
            // Prefer the last heartbeat/event in this session as the real end.
            $lastEvent = LearnerActivityEvent::where('login_session_id', $session->id)
                ->orderByDesc('occurred_at')
                ->value('occurred_at');

            $session->close($lastEvent ?: $session->started_at); // sets duration_seconds / is_bounce
            $closed++;
        }

        return $closed;
    }

    // ─────────────────────────────────────────────────────────────────────
    // EVENT LOGGING HELPERS (call these from controllers/listeners)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Log a raw engagement event. Lightweight — just an insert.
     */
    public function logEvent(
        string  $userId,
        string  $eventType,
        ?string $courseId      = null,
        ?string $resourceType  = null,
        ?string $resourceId    = null,
        ?float  $value         = null,
        array   $metadata      = [],
        string  $deviceType    = 'desktop',
        ?string $loginSessionId = null
    ): LearnerActivityEvent {
        $event = LearnerActivityEvent::create([
            'id'               => Str::uuid()->toString(),
            'user_id'          => $userId,
            'course_id'        => $courseId,
            'login_session_id' => $loginSessionId,
            'event_type'       => $eventType,
            'resource_type'    => $resourceType,
            'resource_id'      => $resourceId,
            'value'            => $value,
            'metadata'         => $metadata ?: null,
            'device_type'      => $deviceType,
            'occurred_at'      => now(),
        ]);

        // Touch streak whenever there's meaningful course activity
        if ($courseId && !in_array($eventType, ['page_idle', 'tab_blur', 'tab_focus', 'logout'])) {
            LearningStreak::record($userId, $courseId);
        }

        return $event;
    }

    /**
     * Open or resume a login session for the authenticated user.
     */
    public function openLoginSession(
        string $userId,
        string $deviceType = 'desktop',
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): LearnerLoginSession {
        $parsed = $this->parseUserAgent($userAgent);

        return LearnerLoginSession::create([
            'id'          => Str::uuid()->toString(),
            'user_id'     => $userId,
            'started_at'  => now(),
            // Prefer a parsed device type from the User-Agent; fall back to the
            // caller-supplied value (defaults to 'desktop').
            'device_type' => $parsed['device_type'] ?? $deviceType,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
            'browser'     => $parsed['browser'],
            'os'          => $parsed['os'],
            'hour_of_day' => (int) now()->format('G'),
        ]);
    }

    /**
     * Best-effort parse of a User-Agent string into device type, browser and OS.
     * Returns nulls when the agent is empty/unrecognized.
     *
     * @return array{device_type: ?string, browser: ?string, os: ?string}
     */
    private function parseUserAgent(?string $ua): array
    {
        if (!$ua) {
            return ['device_type' => null, 'browser' => null, 'os' => null];
        }

        // OS detection
        $os = match (true) {
            (bool) preg_match('/Windows/i', $ua)                 => 'Windows',
            (bool) preg_match('/iPhone|iPad|iPod/i', $ua)        => 'iOS',
            (bool) preg_match('/Mac OS X|Macintosh/i', $ua)      => 'macOS',
            (bool) preg_match('/Android/i', $ua)                 => 'Android',
            (bool) preg_match('/Linux/i', $ua)                   => 'Linux',
            default                                              => 'Other',
        };

        // Browser detection (order matters: Edge/Opera before Chrome, Chrome before Safari)
        $browser = match (true) {
            (bool) preg_match('/Edg|Edge/i', $ua)                => 'Edge',
            (bool) preg_match('/OPR|Opera/i', $ua)               => 'Opera',
            (bool) preg_match('/Chrome|CriOS/i', $ua)            => 'Chrome',
            (bool) preg_match('/Firefox|FxiOS/i', $ua)           => 'Firefox',
            (bool) preg_match('/Safari/i', $ua)                  => 'Safari',
            default                                              => 'Other',
        };

        // Device type
        $deviceType = match (true) {
            (bool) preg_match('/iPad|Tablet/i', $ua)                      => 'tablet',
            (bool) preg_match('/Mobi|Android|iPhone|iPod|Windows Phone/i', $ua) => 'mobile',
            default                                                       => 'desktop',
        };

        return ['device_type' => $deviceType, 'browser' => $browser, 'os' => $os];
    }

    /**
     * Close an existing login session.
     */
    public function closeLoginSession(string $sessionId): void
    {
        $session = LearnerLoginSession::find($sessionId);
        $session?->close();
    }

    /**
     * Record or update a material interaction (video watch %, PDF scroll, etc.).
     */
    public function recordMaterialInteraction(
        string  $materialId,
        string  $studentId,
        string  $courseId,
        float   $completionPercent,
        ?float  $videoWatchPercent = null,
        ?float  $pdfScrollDepth    = null,
        bool    $downloaded        = false,
        int     $durationSeconds   = 0
    ): MaterialInteraction {
        $existing = MaterialInteraction::where('material_id', $materialId)
            ->where('student_id', $studentId)
            ->first();

        if ($existing) {
            $existing->open_count++;
            $existing->last_interaction_at    = now();
            $existing->total_duration_seconds += $durationSeconds;
            $existing->completion_percent      = max($existing->completion_percent, $completionPercent);

            if ($videoWatchPercent !== null) {
                if ($existing->video_watch_percent !== null && $videoWatchPercent < $existing->video_watch_percent) {
                    $existing->rewatch_count++;
                }
                $existing->video_watch_percent = max($existing->video_watch_percent ?? 0, $videoWatchPercent);
            }

            if ($pdfScrollDepth !== null) {
                $existing->pdf_scroll_depth_percent = max($existing->pdf_scroll_depth_percent ?? 0, $pdfScrollDepth);
            }

            if ($downloaded) {
                $existing->downloaded = true;
            }

            $existing->save();
            return $existing;
        }

        return MaterialInteraction::create([
            'id'                       => Str::uuid()->toString(),
            'material_id'              => $materialId,
            'student_id'               => $studentId,
            'course_id'                => $courseId,
            'opened_at'                => now(),
            'last_interaction_at'      => now(),
            'total_duration_seconds'   => $durationSeconds,
            'open_count'               => 1,
            'completion_percent'       => $completionPercent,
            'video_watch_percent'      => $videoWatchPercent,
            'rewatch_count'            => 0,
            'pdf_scroll_depth_percent' => $pdfScrollDepth,
            'downloaded'               => $downloaded,
        ]);
    }
}
