<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EngagementScore;
use App\Models\LearnerActivityEvent;
use App\Models\MaterialInteraction;
use App\Models\LearnerLoginSession;
use App\Models\LearningStreak;
use App\Models\User;
use App\Models\BehavioralSignal;
use App\Models\CognitiveSignal;
use App\Models\StudentGrade;
use App\Models\Section;
use App\Models\Activity;
use App\Models\UserActivityCompletion;
use App\Services\EngagementRecommendationService;
use App\Services\EngagementComputationService;
use App\Services\NotificationService;
use App\Services\GeminiService;

class InstructorEngagementController extends Controller
{
    public function __construct(
        private EngagementRecommendationService $recommender,
        private EngagementComputationService $engagement,
        private NotificationService $notificationService,
        private GeminiService $gemini,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // SHARED HELPERS — component scores are stored 0-100; absent signals are
    // flagged in component_breakdown so the UI shows N/A instead of a fake 0.
    // ─────────────────────────────────────────────────────────────────────

    private function riskLevel(float $score): string
    {
        return match (true) {
            $score >= (float) config('engagement.risk_engaged', 70) => 'engaged',
            $score >= (float) config('engagement.risk_at_risk', 40) => 'at_risk',
            default                                                 => 'disengaged',
        };
    }

    /** Per-signal 0-100 breakdown, with absent (not-applicable) signals as null. */
    private function scoreBreakdown(?EngagementScore $score): array
    {
        $absent = $score?->component_breakdown['absent'] ?? [];
        $val = fn(string $label, ?float $raw) => in_array($label, $absent, true) ? null : round($raw ?? 0, 1);

        return [
            'login_consistency'   => $val('login_consistency',   $score?->login_consistency_score),
            'content_completion'  => $val('content_completion',  $score?->content_completion_score),
            'assessment_activity' => $val('assessment_activity', $score?->assessment_activity_score),
            'forum_participation' => $val('forum_participation', $score?->forum_participation_score),
            'pacing'              => $val('pacing',              $score?->pacing_score),
            'live_session'        => $val('live_session',        $score?->live_session_score),
        ];
    }

    /** Measured-vs-assumed confidence summary for an engagement score. */
    private function confidence(?EngagementScore $score): array
    {
        $b = $score?->component_breakdown ?? [];
        $measured = $b['measured'] ?? [];

        return [
            'measured'   => $measured,
            'absent'     => $b['absent'] ?? [],
            'confidence' => $b['confidence'] ?? 0,
            'label'      => count($measured) . '/6 signals',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // COURSE OVERVIEW
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/instructor/courses/{courseId}/engagement
     * All enrolled learners with latest engagement scores and risk levels.
     */
    public function courseOverview(Request $request, string $courseId): JsonResponse
    {
        Course::findOrFail($courseId);

        $enrollments = Enrollment::where('course_id', $courseId)
            ->with('user:id,name,email,profile_image')
            ->get();

        $learners = $enrollments->map(function ($enrollment) use ($courseId) {
            $userId = $enrollment->user_id;

            $latestScore = EngagementScore::where('learner_id', $userId)
                ->where('course_id', $courseId)
                ->orderByDesc('week_number')
                ->first();

            $streak = LearningStreak::where('learner_id', $userId)
                ->where('course_id', $courseId)
                ->first();

            $lastLogin = LearnerLoginSession::where('user_id', $userId)
                ->orderByDesc('started_at')
                ->first();

            $finalScore    = round($latestScore?->engagement_score ?? 0, 1);
            $inactiveDays  = $lastLogin
                ? (int) Carbon::parse($lastLogin->started_at)->diffInDays(now())
                : null;

            return [
                'user_id'         => $userId,
                'name'            => $enrollment->user?->name,
                'email'           => $enrollment->user?->email,
                'profile_image'   => $enrollment->user?->profile_image,
                'engagement_score'=> $finalScore,
                'has_data'        => $latestScore !== null,
                'risk_level'      => $this->riskLevel($finalScore),
                'streak'          => $streak?->current_streak_days ?? 0,
                'last_login'      => $lastLogin?->started_at,
                'inactive_days'   => $inactiveDays,
                'week_number'     => $latestScore?->week_number,
                'score_breakdown' => $this->scoreBreakdown($latestScore),
                'confidence'      => $this->confidence($latestScore),
            ];
        });

        $count = $learners->count();

        // Real active time-on-task across the class (last 30d): heartbeat seconds + material time.
        $since = now()->subDays(30);
        $activeSeconds = (float) LearnerActivityEvent::where('course_id', $courseId)
                ->where('event_type', 'heartbeat')
                ->where('occurred_at', '>=', $since)
                ->sum('value')
            + (float) MaterialInteraction::where('course_id', $courseId)
                ->where('last_interaction_at', '>=', $since)
                ->sum('total_duration_seconds');

        $summary = [
            'total'              => $count,
            'engaged'            => $learners->where('risk_level', 'engaged')->count(),
            'at_risk'            => $learners->where('risk_level', 'at_risk')->count(),
            'disengaged'         => $learners->where('risk_level', 'disengaged')->count(),
            'avg_score'          => $count ? round($learners->avg('engagement_score'), 1) : 0,
            // Average measured active minutes per learner, and average signal coverage (0-1).
            'avg_active_minutes' => $count ? round(($activeSeconds / 60) / $count, 1) : 0,
            'coverage'           => $count ? round($learners->avg(fn($l) => $l['confidence']['confidence'] ?? 0), 2) : 0,
        ];

        return response()->json([
            'data'    => $learners->sortByDesc('engagement_score')->values(),
            'summary' => $summary,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // AT-RISK LEARNERS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/instructor/courses/{courseId}/engagement/at-risk
     * Only at-risk and disengaged learners with AI intervention suggestions.
     */
    public function atRisk(Request $request, string $courseId): JsonResponse
    {
        Course::findOrFail($courseId);

        $enrollments = Enrollment::where('course_id', $courseId)
            ->with('user:id,name,email,profile_image')
            ->get();

        $atRisk = $enrollments->map(function ($enrollment) use ($courseId) {
            $userId = $enrollment->user_id;

            $latestScore = EngagementScore::where('learner_id', $userId)
                ->where('course_id', $courseId)
                ->orderByDesc('week_number')
                ->first();

            $lastLogin = LearnerLoginSession::where('user_id', $userId)
                ->orderByDesc('started_at')
                ->first();

            $finalScore   = round($latestScore?->engagement_score ?? 0, 1);
            $inactiveDays = $lastLogin
                ? (int) Carbon::parse($lastLogin->started_at)->diffInDays(now())
                : null;

            $riskLevel = $this->riskLevel($finalScore);

            if ($riskLevel === 'engaged') return null;

            $interventions = $this->recommender->forInstructor($userId, $courseId);

            return [
                'user_id'         => $userId,
                'name'            => $enrollment->user?->name,
                'email'           => $enrollment->user?->email,
                'profile_image'   => $enrollment->user?->profile_image,
                'engagement_score'=> $finalScore,
                'has_data'        => $latestScore !== null,
                'risk_level'      => $riskLevel,
                'inactive_days'   => $inactiveDays,
                'last_login'      => $lastLogin?->started_at,
                'score_breakdown' => $this->scoreBreakdown($latestScore),
                'confidence'      => $this->confidence($latestScore),
                'interventions'   => $interventions,
                'reasons'         => $this->buildRiskReasons($latestScore, $inactiveDays),
            ];
        })->filter()->sortBy('engagement_score')->values();

        return response()->json(['data' => $atRisk, 'count' => $atRisk->count()]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // INDIVIDUAL LEARNER DETAIL
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/instructor/courses/{courseId}/learners/{userId}/engagement
     * Full engagement profile for a specific learner.
     */
    public function learnerDetail(Request $request, string $courseId, string $userId): JsonResponse
    {
        Course::findOrFail($courseId);
        $user = User::findOrFail($userId);

        // Weekly score history (last 10 weeks)
        $scoreHistory = EngagementScore::where('learner_id', $userId)
            ->where('course_id', $courseId)
            ->orderBy('week_number')
            ->take(10)
            ->get();

        // Login history (last 20)
        $loginHistory = LearnerLoginSession::where('user_id', $userId)
            ->orderByDesc('started_at')
            ->take(20)
            ->get();

        // Recent activity events (last 30)
        $activityLog = LearnerActivityEvent::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->orderByDesc('occurred_at')
            ->take(30)
            ->get();

        $streak = LearningStreak::where('learner_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        $latestScore = $scoreHistory->last();
        $finalScore  = round($latestScore?->engagement_score ?? 0, 1);

        $lastLogin   = $loginHistory->first();
        $inactiveDays = $lastLogin
            ? (int) Carbon::parse($lastLogin->started_at)->diffInDays(now())
            : null;

        $riskLevel = $this->riskLevel($finalScore);

        // Device breakdown
        $deviceBreakdown = LearnerLoginSession::where('user_id', $userId)
            ->where('started_at', '>=', now()->subDays(30))
            ->selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->pluck('count', 'device_type');

        // Real active time-on-task (measured from heartbeats + material interactions)
        $timeOnTask = $this->engagement->timeOnTask($userId, $courseId, 30);

        // AI interventions
        $interventions = $this->recommender->forInstructor($userId, $courseId);

        return response()->json([
            'user'             => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'profile_image' => $user->profile_image,
            ],
            'engagement_score' => $finalScore,
            'risk_level'       => $riskLevel,
            'score_breakdown'  => $this->scoreBreakdown($latestScore),
            'confidence'       => $this->confidence($latestScore),
            'streak'           => $streak,
            'inactive_days'    => $inactiveDays,
            'score_history'    => $scoreHistory,
            'login_history'    => $loginHistory,
            'activity_log'     => $activityLog,
            'device_breakdown' => $deviceBreakdown,
            'time_on_task'     => $timeOnTask,
            'interventions'    => $interventions,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // NUDGE (AI-DRIVEN NOTIFICATION)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/instructor/courses/{courseId}/learners/{userId}/nudge
     * Send an AI-generated re-engagement notification to a learner.
     */
    public function nudgeLearner(Request $request, string $courseId, string $userId): JsonResponse
    {
        $course  = Course::findOrFail($courseId);
        $student = User::findOrFail($userId);

        // ── Collect comprehensive student context ────────────────────────
        $enrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)->first();

        $engScore = EngagementScore::where('learner_id', $userId)
            ->where('course_id', $courseId)
            ->orderByDesc('week_number')->first();

        $prevScore = $engScore
            ? EngagementScore::where('learner_id', $userId)
                ->where('course_id', $courseId)
                ->where('week_number', $engScore->week_number - 1)
                ->first()
            : null;

        $streak    = LearningStreak::where('learner_id', $userId)
            ->orderByDesc('last_active_date')->first();

        $lastLogin = LearnerLoginSession::where('user_id', $userId)
            ->orderByDesc('started_at')->first();

        $behavioral = BehavioralSignal::where('learner_id', $userId)
            ->orderByDesc('week_number')->first();

        $inactiveDays = $lastLogin
            ? (int) Carbon::parse($lastLogin->started_at)->diffInDays(now())
            : 0;

        $engVal    = $engScore?->engagement_score ?? 0;
        $riskLevel = $engVal >= 70 ? 'low' : ($engVal >= 40 ? 'medium' : 'high');

        // Derive strengths and weak areas from sub-scores
        $subScores = [
            'login_consistency'   => $engScore?->login_consistency_score,
            'content_completion'  => $engScore?->content_completion_score,
            'assessment_activity' => $engScore?->assessment_activity_score,
            'forum_participation' => $engScore?->forum_participation_score,
            'pacing'              => $engScore?->pacing_score,
            'live_session'        => $engScore?->live_session_score,
        ];
        $absentSignals = $engScore?->component_breakdown['absent'] ?? [];
        $strengths = [];
        $weakAreas = [];
        foreach ($subScores as $key => $val) {
            if ($val === null || in_array($key, $absentSignals, true)) continue;
            $label = ucwords(str_replace('_', ' ', $key));
            // Component scores are 0-100.
            if ($val >= 70) $strengths[] = $label;
            if ($val <  40) $weakAreas[]  = $label;
        }
        if ($prevScore && ($prevScore->engagement_score - $engVal) >= 15) {
            $weakAreas[] = 'Engagement dropped ' . round($prevScore->engagement_score - $engVal) . ' pts week-over-week';
        }

        $avgGrade = StudentGrade::where('student_id', $userId)
            ->whereHas('gradeItem', fn($q) => $q->where('course_id', $courseId))
            ->avg('score');

        // ── Course content context (sections + activities) ───────────────────
        $sections = Section::where('course_id', $courseId)
            ->orderBy('sort_order')
            ->with(['activities' => fn($q) => $q->select('id', 'section_id', 'name', 'type')->orderBy('sort_order')])
            ->get(['id', 'title', 'summary']);

        $completedIds = UserActivityCompletion::where('user_id', $userId)
            ->whereIn('activity_id', $sections->pluck('activities')->flatten()->pluck('id'))
            ->pluck('activity_id')->toArray();

        $courseTopics     = [];
        $completedTopics  = [];
        $incompleteTopics = [];

        foreach ($sections as $sec) {
            $sectionActivities = $sec->activities ?? collect();
            $courseTopics[]    = $sec->title;
            foreach ($sectionActivities as $act) {
                if (in_array($act->id, $completedIds)) {
                    $completedTopics[]  = $act->name;
                } else {
                    $incompleteTopics[] = $act->name;
                }
            }
        }

        $studentData = [
            'name'                => $student->name,
            'course_name'         => $course->name,
            'progress'            => (int) ($enrollment?->progress ?? 0),
            'current_grade'       => $avgGrade ? round($avgGrade, 1) . '%' : 'N/A',
            'engagement_score'    => round($engVal, 1),
            'risk_level'          => $riskLevel,
            'streak_days'         => $streak?->current_streak ?? 0,
            'inactive_days'       => $inactiveDays,
            'vark_style'          => $student->vark_style,
            'preferred_modes'     => $student->preferred_modes ?? [],
            'pace_preference'     => $student->pace_preference,
            'declared_interests'  => $student->declared_interests ?? [],
            'support_notes'       => $student->support_notes,
            'strengths'           => $strengths,
            'weak_areas'          => $weakAreas,
            'login_consistency'   => $engScore ? round($engScore->login_consistency_score ?? 0) . '%' : 'N/A',
            'content_completion'  => $engScore ? round($engScore->content_completion_score ?? 0) . '%' : 'N/A',
            'assessment_activity' => $engScore ? round($engScore->assessment_activity_score ?? 0) . '%' : 'N/A',
            'forum_participation' => in_array('forum_participation', $absentSignals, true) ? 'N/A' : ($engScore ? round($engScore->forum_participation_score ?? 0) . '%' : 'N/A'),
            'live_session_score'  => in_array('live_session', $absentSignals, true) ? 'N/A' : ($engScore ? round($engScore->live_session_score ?? 0) . '%' : 'N/A'),
            'bounce_sessions'     => $behavioral?->bounce_session_count ?? 0,
            'avg_session_minutes' => $behavioral?->avg_session_duration_minutes
                ? round($behavioral->avg_session_duration_minutes, 1) : 'N/A',
            'course_description'  => $course->description ?? null,
            'course_topics'       => $courseTopics,
            'completed_topics'    => array_slice($completedTopics, -5),   // last 5 completed
            'incomplete_topics'   => array_slice($incompleteTopics, 0, 5), // next 5 to do
        ];

        // ── Generate personalised nudge via Gemini ───────────────────────
        try {
            $nudgeContent = $this->gemini->generatePersonalizedNudge($studentData);
        } catch (\Throwable $e) {
            Log::warning("Gemini nudge failed for user {$userId}: " . $e->getMessage());
            $interventions = $this->recommender->forInstructor($userId, $courseId);
            $top           = $interventions[0] ?? null;
            $nudgeContent  = $top
                ? $top['suggestion']
                : "Your instructor wants to check in on your progress in {$course->name}. Please log in and catch up!";
        }

        // ── Send as in-app notification ──────────────────────────────────
        try {
            $instructorName = $request->user()->name ?? 'Your Instructor';
            $this->notificationService->sendToUser(
                $userId,
                'engagement_nudge',
                'in_app',
                "A note from {$instructorName} — {$course->name}",
                $nudgeContent,
                ['course_id' => $courseId]
            );

            Log::info("AI nudge sent to user {$userId} for course {$courseId}");

            return response()->json([
                'message' => 'AI nudge sent successfully.',
                'user_id' => $userId,
                'content' => $nudgeContent,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to send nudge: ' . $e->getMessage()], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────────────────

    private function buildRiskReasons(?EngagementScore $score, ?int $inactiveDays): array
    {
        $reasons = [];
        if (!$score) {
            $reasons[] = 'No engagement data recorded';
            return $reasons;
        }

        // Component scores are stored on a 0-100 scale. Only flag a signal that
        // was actually measured (absent signals carry a placeholder 0).
        $absent = $score->component_breakdown['absent'] ?? [];
        $warn   = (int) config('engagement.inactivity_warn', 5);

        if ($inactiveDays !== null && $inactiveDays >= $warn) $reasons[] = "Inactive for {$inactiveDays} days";
        if (($score->login_consistency_score   ?? 100) < 40) $reasons[] = 'Low login consistency';
        if (($score->content_completion_score  ?? 100) < 50) $reasons[] = 'Low content completion';
        if (($score->assessment_activity_score ?? 100) < 30) $reasons[] = 'Very low assessment activity';
        if (!in_array('forum_participation', $absent, true) && ($score->forum_participation_score ?? 100) == 0) {
            $reasons[] = 'Zero forum participation';
        }
        if (!in_array('live_session', $absent, true) && ($score->live_session_score ?? 100) == 0) {
            $reasons[] = 'No live session attendance';
        }
        return $reasons;
    }
}
