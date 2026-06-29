<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\EngagementScore;
use App\Models\LearnerLoginSession;
use App\Models\LearnerActivityEvent;
use App\Models\LearningStreak;
use App\Models\CognitiveSignal;
use App\Models\BehavioralSignal;
use App\Models\Enrollment;
use App\Models\Activity;
use App\Models\Session;

class EngagementRecommendationService
{
    // Score thresholds
    private const SCORE_ENGAGED     = 70;
    private const SCORE_AT_RISK     = 40;
    private const INACTIVITY_WARN   = 5;   // days
    private const INACTIVITY_DANGER = 10;  // days
    private const SKIP_RATE_HIGH    = 30;  // %
    private const BOUNCE_RATE_HIGH  = 3;   // sessions
    private const SCORE_DROP_ALERT  = 15;  // points week-over-week

    /**
     * Generate AI recommendation cards for a learner (all courses).
     * Returns array of recommendation cards ordered by priority.
     */
    public function forLearner(string $learnerId): array
    {
        $recs = [];

        // Latest score per course
        $latestScores = EngagementScore::where('learner_id', $learnerId)
            ->orderBy('week_number', 'desc')
            ->get()
            ->groupBy('course_id')
            ->map(fn($g) => $g->first());

        // Streak data
        $streak = LearningStreak::where('learner_id', $learnerId)
            ->orderBy('last_active_date', 'desc')
            ->first();

        // Last login
        $lastLogin = LearnerLoginSession::where('user_id', $learnerId)
            ->orderByDesc('started_at')
            ->first();

        $inactiveDays = $lastLogin
            ? (int) Carbon::parse($lastLogin->started_at)->diffInDays(now())
            : null;

        // Behavioral signals
        $behavioral = BehavioralSignal::where('learner_id', $learnerId)
            ->orderBy('week_number', 'desc')
            ->first();

        // Cognitive signals
        $cognitive = CognitiveSignal::where('learner_id', $learnerId)
            ->orderBy('week_number', 'desc')
            ->first();

        // ── Inactivity rules ──────────────────────────────────────────────
        if ($inactiveDays !== null && $inactiveDays >= self::INACTIVITY_DANGER) {
            $recs[] = $this->card(
                type: 'danger',
                title: 'Extended Inactivity Detected',
                message: "You haven't logged in for {$inactiveDays} days. Consistent engagement is critical for learning retention. Log in now and start where you left off.",
                action: 'Resume Learning',
                metric: 'login_consistency',
                priority: 10,
                actionType: 'courses',
            );
        } elseif ($inactiveDays !== null && $inactiveDays >= self::INACTIVITY_WARN) {
            $recs[] = $this->card(
                type: 'warning',
                title: 'You\'ve Been Away for a While',
                message: "You last logged in {$inactiveDays} days ago. Try to access your course at least 3 times per week to stay on track.",
                action: 'Go to My Courses',
                metric: 'login_consistency',
                priority: 8,
                actionType: 'courses',
            );
        }

        // ── Streak encouragement ──────────────────────────────────────────
        if ($streak && $streak->current_streak >= 7) {
            $recs[] = $this->card(
                type: 'success',
                title: "🔥 {$streak->current_streak}-Day Learning Streak!",
                message: "Amazing dedication! You've been active for {$streak->current_streak} consecutive days. Keep it up to beat your record of {$streak->longest_streak} days.",
                metric: 'streak',
                priority: 2,
            );
        } elseif ($streak && $streak->current_streak >= 3) {
            $recs[] = $this->card(
                type: 'info',
                title: "{$streak->current_streak}-Day Streak — Keep Going!",
                message: "You're building a great habit. Log in tomorrow to reach a 1-week streak milestone!",
                metric: 'streak',
                priority: 1,
            );
        }

        // ── Upcoming deadline (closes the loop with the calendar) ──────────
        $enrolledCourseIds = Enrollment::where('user_id', $learnerId)
            ->where('status', 'active')
            ->pluck('course_id');

        if ($enrolledCourseIds->isNotEmpty()) {
            $soon = now()->copy()->addDays(7);

            $nextDue = Activity::whereIn('course_id', $enrolledCourseIds)
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [now()->startOfDay(), $soon])
                ->orderBy('due_date')
                ->first(['id', 'name', 'due_date', 'course_id']);

            $nextSession = Session::whereIn('course_id', $enrolledCourseIds)
                ->where('status', '!=', 'ended')
                ->whereNotNull('scheduled_at')
                ->whereBetween('scheduled_at', [now(), $soon])
                ->orderBy('scheduled_at')
                ->first(['id', 'title', 'scheduled_at', 'course_id']);

            $dueWhen     = $nextDue ? Carbon::parse($nextDue->due_date) : null;
            $sessionWhen = $nextSession ? Carbon::parse($nextSession->scheduled_at) : null;

            // Surface whichever lands first.
            if ($dueWhen && (!$sessionWhen || $dueWhen->lessThanOrEqualTo($sessionWhen))) {
                $days = max(0, (int) now()->startOfDay()->diffInDays($dueWhen, false));
                $when = $days <= 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days");
                $recs[] = $this->card(
                    type: 'info',
                    title: 'Upcoming Deadline',
                    message: "\"{$nextDue->name}\" is due {$when}. Open your calendar to plan your week and stay ahead.",
                    metric: 'pacing',
                    action: 'View Schedule',
                    priority: 6,
                    actionType: 'calendar',
                    actionTarget: ['course_id' => $nextDue->course_id],
                );
            } elseif ($sessionWhen) {
                $days = max(0, (int) now()->startOfDay()->diffInDays($sessionWhen, false));
                $when = $days <= 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in {$days} days");
                $recs[] = $this->card(
                    type: 'info',
                    title: 'Live Session Coming Up',
                    message: "\"{$nextSession->title}\" is scheduled {$when}. Add it to your plan so you don't miss the live Q&A.",
                    metric: 'live_session',
                    action: 'View Schedule',
                    priority: 5,
                    actionType: 'calendar',
                    actionTarget: ['course_id' => $nextSession->course_id],
                );
            }
        }

        // ── Per-course score rules ────────────────────────────────────────
        foreach ($latestScores as $courseId => $score) {
            $courseName = $score->course?->name ?? 'your course';

            // Low login consistency
            if ($score->login_consistency_score !== null && $score->login_consistency_score < 0.4) {
                $pct = round($score->login_consistency_score * 100);
                $recs[] = $this->card(
                    type: 'warning',
                    title: 'Low Login Consistency',
                    message: "Your login consistency for {$courseName} is {$pct}%. Aim to access the course at least 4 days per week.",
                    metric: 'login_consistency',
                    priority: 7,
                );
            }

            // Low content completion
            if ($score->content_completion_score !== null && $score->content_completion_score < 0.5) {
                $pct = round($score->content_completion_score * 100);
                $recs[] = $this->card(
                    type: 'warning',
                    title: 'Course Materials Incomplete',
                    message: "You've completed only {$pct}% of the expected content in {$courseName}. Try completing at least one activity per day.",
                    action: 'View Course',
                    metric: 'content_completion',
                    priority: 7,
                    actionType: 'lessons',
                    actionTarget: ['course_id' => $courseId],
                );
            }

            // Low assessment activity
            if ($score->assessment_activity_score !== null && $score->assessment_activity_score < 0.4) {
                $recs[] = $this->card(
                    type: 'warning',
                    title: 'Quizzes & Assignments Need Attention',
                    message: "Your assessment participation in {$courseName} is low. Complete pending quizzes and assignments to boost your grade.",
                    action: 'View Assessments',
                    metric: 'assessment_activity',
                    priority: 9,
                    actionType: 'assessments',
                    actionTarget: ['course_id' => $courseId],
                );
            }

            // Zero forum participation
            if ($score->forum_participation_score !== null && $score->forum_participation_score == 0) {
                $recs[] = $this->card(
                    type: 'info',
                    title: 'Join the Course Discussion',
                    message: "You haven't posted in {$courseName} forums yet. Students who participate in discussions score 15–25% higher on average.",
                    action: 'Go to Forum',
                    metric: 'forum_participation',
                    priority: 4,
                    actionType: 'forum',
                    actionTarget: ['course_id' => $courseId],
                );
            }

            // Low pacing score
            if ($score->pacing_score !== null && $score->pacing_score < 0.4) {
                $recs[] = $this->card(
                    type: 'warning',
                    title: 'Falling Behind the Course Pace',
                    message: "Your pacing score for {$courseName} suggests you may miss upcoming deadlines. Check your course schedule and catch up.",
                    action: 'View Schedule',
                    metric: 'pacing',
                    priority: 8,
                    actionType: 'calendar',
                    actionTarget: ['course_id' => $courseId],
                );
            }

            // Zero live session attendance
            if ($score->live_session_score !== null && $score->live_session_score == 0) {
                $recs[] = $this->card(
                    type: 'info',
                    title: 'Attend a Live Session',
                    message: "You haven't attended any live sessions in {$courseName}. Live sessions provide real-time Q&A and deeper understanding.",
                    action: 'View Sessions',
                    metric: 'live_session',
                    priority: 3,
                    actionType: 'sessions',
                    actionTarget: ['course_id' => $courseId],
                );
            }

            // Week-over-week score drop
            $prevScore = EngagementScore::where('learner_id', $learnerId)
                ->where('course_id', $courseId)
                ->where('week_number', $score->week_number - 1)
                ->first();

            if ($prevScore && ($prevScore->engagement_score - $score->engagement_score) >= self::SCORE_DROP_ALERT) {
                $drop = round($prevScore->engagement_score - $score->engagement_score, 1);
                $recs[] = $this->card(
                    type: 'danger',
                    title: 'Engagement Score Dropped Significantly',
                    message: "Your engagement score in {$courseName} dropped by {$drop} points this week. Review your learning habits and try to be more consistent.",
                    metric: 'overall',
                    priority: 10,
                );
            }

            // High score — encouragement
            if ($score->engagement_score >= self::SCORE_ENGAGED) {
                $recs[] = $this->card(
                    type: 'success',
                    title: 'Excellent Engagement in ' . $courseName,
                    message: "Your engagement score of " . round($score->engagement_score, 1) . " is outstanding! You're in the top tier of engaged learners.",
                    metric: 'overall',
                    priority: 1,
                );
            }
        }

        // ── Behavioral signal rules ───────────────────────────────────────
        if ($behavioral) {
            if (($behavioral->bounce_session_count ?? 0) >= self::BOUNCE_RATE_HIGH) {
                $recs[] = $this->card(
                    type: 'warning',
                    title: 'Short Login Sessions Detected',
                    message: "You have {$behavioral->bounce_session_count} very short login sessions (under 2 minutes). Try to study in longer, focused blocks for better retention.",
                    metric: 'behavioral',
                    priority: 5,
                );
            }

            if ($behavioral->primary_device_type === 'mobile' && ($behavioral->average_video_watch_percent ?? 100) < 50) {
                $recs[] = $this->card(
                    type: 'info',
                    title: 'Switch to Desktop for Video Content',
                    message: "Your video completion rate is low on mobile. Try watching course videos on a desktop or laptop for a better learning experience.",
                    metric: 'behavioral',
                    priority: 3,
                );
            }
        }

        // ── Cognitive signal rules ────────────────────────────────────────
        if ($cognitive) {
            if (($cognitive->quiz_question_skip_rate ?? 0) >= self::SKIP_RATE_HIGH) {
                $skipRate = round($cognitive->quiz_question_skip_rate, 1);
                $recs[] = $this->card(
                    type: 'warning',
                    title: 'High Quiz Question Skip Rate',
                    message: "You're skipping {$skipRate}% of quiz questions. Review the learning material before attempting quizzes to improve your score.",
                    action: 'Review Materials',
                    metric: 'cognitive',
                    priority: 6,
                    actionType: 'lessons',
                );
            }

            if (($cognitive->assignment_revision_count ?? 0) >= 3) {
                $recs[] = $this->card(
                    type: 'info',
                    title: 'Multiple Assignment Revisions',
                    message: "You've revised assignments {$cognitive->assignment_revision_count} times. Consider seeking instructor feedback before resubmitting.",
                    metric: 'cognitive',
                    priority: 3,
                );
            }
        }

        // Sort by priority descending and deduplicate metrics
        usort($recs, fn($a, $b) => $b['priority'] <=> $a['priority']);

        // Return top 8 recommendations
        return array_slice($recs, 0, 8);
    }

    /**
     * Generate AI intervention suggestions for a specific learner in a course.
     * Used by the instructor view.
     */
    public function forInstructor(string $learnerId, string $courseId): array
    {
        $score = EngagementScore::where('learner_id', $learnerId)
            ->where('course_id', $courseId)
            ->orderBy('week_number', 'desc')
            ->first();

        $lastLogin = LearnerLoginSession::where('user_id', $learnerId)
            ->orderByDesc('started_at')
            ->first();

        $inactiveDays = $lastLogin
            ? (int) Carbon::parse($lastLogin->started_at)->diffInDays(now())
            : null;

        $interventions = [];

        if (!$score) {
            $interventions[] = [
                'type' => 'danger',
                'title' => 'No Engagement Data',
                'suggestion' => 'This learner has no recorded engagement data. Consider reaching out to confirm enrollment and access.',
                'action' => 'send_nudge',
                'priority' => 10,
            ];
            return $interventions;
        }

        $finalScore = $score->engagement_score ?? 0;

        if ($finalScore < self::SCORE_AT_RISK) {
            $interventions[] = [
                'type' => 'danger',
                'title' => 'Critical — At High Risk of Dropout',
                'suggestion' => "Engagement score is {$finalScore}/100. Send a personalised message immediately and consider a one-on-one check-in.",
                'action' => 'send_nudge',
                'priority' => 10,
            ];
        }

        if ($inactiveDays !== null && $inactiveDays >= self::INACTIVITY_DANGER) {
            $interventions[] = [
                'type' => 'danger',
                'title' => "Inactive for {$inactiveDays} Days",
                'suggestion' => 'Send a re-engagement notification. If no response in 3 days, escalate to programme coordinator.',
                'action' => 'send_nudge',
                'priority' => 9,
            ];
        }

        if ($score->forum_participation_score == 0) {
            $interventions[] = [
                'type' => 'warning',
                'title' => 'Zero Forum Participation',
                'suggestion' => 'Assign a discussion prompt directly to this student or pair them with an active peer for a forum task.',
                'action' => 'send_nudge',
                'priority' => 5,
            ];
        }

        if ($score->assessment_activity_score !== null && $score->assessment_activity_score < 0.3) {
            $interventions[] = [
                'type' => 'warning',
                'title' => 'Very Low Assessment Participation',
                'suggestion' => 'Send a reminder about pending quizzes/assignments. Check if the learner is having technical difficulties.',
                'action' => 'send_nudge',
                'priority' => 8,
            ];
        }

        if ($score->live_session_score == 0) {
            $interventions[] = [
                'type' => 'info',
                'title' => 'No Live Session Attendance',
                'suggestion' => 'Send session schedule details and encourage attendance. Consider sharing session recordings.',
                'action' => 'send_nudge',
                'priority' => 4,
            ];
        }

        if ($finalScore >= self::SCORE_ENGAGED) {
            $interventions[] = [
                'type' => 'success',
                'title' => 'Highly Engaged Learner',
                'suggestion' => 'This learner is performing well. Consider recognising their effort with a badge or highlighting their forum contributions.',
                'action' => null,
                'priority' => 1,
            ];
        }

        usort($interventions, fn($a, $b) => $b['priority'] <=> $a['priority']);
        return array_slice($interventions, 0, 5);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function card(
        string $type,
        string $title,
        string $message,
        string $metric = 'general',
        ?string $action = null,
        int $priority = 5,
        ?string $actionType = null,
        array $actionTarget = [],
    ): array {
        return [
            'type'          => $type,
            'title'         => $title,
            'message'       => $message,
            'metric'        => $metric,
            'action'        => $action,
            'action_type'   => $actionType,
            'action_target' => (object) $actionTarget,
            'priority'      => $priority,
        ];
    }
}
