<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Models\EngagementScore;
use App\Models\LearnerActivityEvent;
use App\Models\LearnerLoginSession;
use App\Models\LearningStreak;
use App\Models\MaterialInteraction;
use App\Models\Course;
use App\Models\Enrollment;
use App\Services\EngagementComputationService;
use App\Services\EngagementRecommendationService;

class EngagementController extends Controller
{
    public function __construct(private EngagementComputationService $service) {}

    // ─────────────────────────────────────────────────────────────────────
    // EVENT LOGGING
    // ─────────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/engagement/events
     * Log a raw learner activity event from the frontend.
     */
    public function logEvent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_type'       => 'required|string|max:50',
            'course_id'        => 'sometimes|nullable|uuid|exists:courses,id',
            'resource_type'    => 'sometimes|nullable|string|max:40',
            'resource_id'      => 'sometimes|nullable|uuid',
            'value'            => 'sometimes|nullable|numeric',
            'metadata'         => 'sometimes|nullable|array',
            'device_type'      => 'sometimes|string|in:desktop,mobile,tablet',
            'login_session_id' => 'sometimes|nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $event = $this->service->logEvent(
            userId:         $request->user()->id,
            eventType:      $request->event_type,
            courseId:       $request->input('course_id'),
            resourceType:   $request->input('resource_type'),
            resourceId:     $request->input('resource_id'),
            value:          $request->input('value'),
            metadata:       $request->input('metadata', []),
            deviceType:     $request->input('device_type', 'desktop'),
            loginSessionId: $request->input('login_session_id'),
        );

        return response()->json(['message' => 'Event logged.', 'data' => $event], 201);
    }

    // ─────────────────────────────────────────────────────────────────────
    // LOGIN SESSION MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/engagement/session/open
     * Open a new login session (call on app load / after auth).
     */
    public function openSession(Request $request): JsonResponse
    {
        $session = $this->service->openLoginSession(
            $request->user()->id,
            $request->input('device_type', 'desktop'),
            $request->ip(),
            $request->userAgent()
        );

        return response()->json(['message' => 'Session opened.', 'session_id' => $session->id], 201);
    }

    /**
     * POST /api/v1/engagement/session/close
     * Close the current login session (call on logout / page unload).
     */
    public function closeSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->service->closeLoginSession($request->session_id);

        return response()->json(['message' => 'Session closed.']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // MATERIAL INTERACTION
    // ─────────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/engagement/materials/{materialId}/interact
     * Record or update a material interaction (video watch %, PDF scroll, etc.).
     */
    public function recordMaterialInteraction(Request $request, string $materialId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_id'           => 'required|uuid|exists:courses,id',
            'completion_percent'  => 'required|numeric|min:0|max:100',
            'video_watch_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'pdf_scroll_depth'    => 'sometimes|nullable|numeric|min:0|max:100',
            'downloaded'          => 'sometimes|boolean',
            'duration_seconds'    => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $interaction = $this->service->recordMaterialInteraction(
            materialId:        $materialId,
            studentId:         $request->user()->id,
            courseId:          $request->course_id,
            completionPercent: $request->completion_percent,
            videoWatchPercent: $request->input('video_watch_percent'),
            pdfScrollDepth:    $request->input('pdf_scroll_depth'),
            downloaded:        $request->boolean('downloaded', false),
            durationSeconds:   $request->input('duration_seconds', 0),
        );

        return response()->json(['message' => 'Interaction recorded.', 'data' => $interaction]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ENGAGEMENT SCORE COMPUTATION & RETRIEVAL
    // ─────────────────────────────────────────────────────────────────────

    /**
     * POST /api/v1/courses/{courseId}/learners/{userId}/engagement/compute
     * Trigger computation of engagement score for a specific week.
     * Admin / Instructor only.
     */
    public function compute(Request $request, string $courseId, string $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'week_number' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Course::findOrFail($courseId);

        $score = $this->service->computeForWeek($userId, $courseId, $request->week_number);

        return response()->json(['message' => 'Engagement score computed.', 'data' => $score], 201);
    }

    /**
     * GET /api/v1/courses/{courseId}/learners/{userId}/engagement
     * Get all weekly engagement scores for a learner in a course.
     */
    public function learnerScores(Request $request, string $courseId, string $userId): JsonResponse
    {
        $scores = EngagementScore::where('learner_id', $userId)
            ->where('course_id', $courseId)
            ->orderBy('week_number')
            ->get();

        return response()->json(['data' => $scores, 'course_id' => $courseId, 'user_id' => $userId]);
    }

    /**
     * GET /api/v1/courses/{courseId}/engagement
     * Get the latest engagement score for every learner in a course.
     * Admin / Instructor only.
     */
    public function courseScores(Request $request, string $courseId): JsonResponse
    {
        Course::findOrFail($courseId);

        $week = $request->query('week');

        $query = EngagementScore::where('course_id', $courseId)
            ->with('learner:id,name,email');

        if ($week) {
            $query->where('week_number', $week);
        } else {
            // Latest week per learner
            $query->whereIn('id', function ($sub) use ($courseId) {
                $sub->selectRaw('MAX(id)')
                    ->from('engagement_scores')
                    ->where('course_id', $courseId)
                    ->groupBy('learner_id');
            });
        }

        $data = $query->orderBy('engagement_score', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data'      => $data->items(),
            'course_id' => $courseId,
            'meta'      => [
                'total'        => $data->total(),
                'per_page'     => $data->perPage(),
                'current_page' => $data->currentPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/engagement/my-score?course_id={id}&week={n}
     * Student's own latest engagement score.
     */
    public function myScore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|uuid|exists:courses,id',
            'week'      => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = EngagementScore::where('learner_id', $request->user()->id)
            ->where('course_id', $request->course_id);

        $score = $request->filled('week')
            ? $query->where('week_number', $request->week)->first()
            : $query->orderBy('week_number', 'desc')->first();

        return response()->json(['data' => $score]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // LEARNING STREAK
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/engagement/streak?course_id={id}
     * Get the authenticated student's learning streak for a course.
     */
    public function myStreak(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|uuid|exists:courses,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $streak = LearningStreak::where('learner_id', $request->user()->id)
            ->where('course_id', $request->course_id)
            ->first();

        return response()->json(['data' => $streak]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // MATERIAL INTERACTIONS (read)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/courses/{courseId}/learners/{userId}/material-interactions
     * Instructor view of all material interactions for a learner.
     */
    public function materialInteractions(string $courseId, string $userId): JsonResponse
    {
        $interactions = MaterialInteraction::where('student_id', $userId)
            ->where('course_id', $courseId)
            ->with('material:id,title,type')
            ->get();

        return response()->json(['data' => $interactions, 'course_id' => $courseId, 'user_id' => $userId]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // RECENT EVENTS (read)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/courses/{courseId}/learners/{userId}/activity-events
     * Paginated raw event log for a learner in a course.
     */
    public function activityEvents(Request $request, string $courseId, string $userId): JsonResponse
    {
        $events = LearnerActivityEvent::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->when($request->filled('event_type'), fn($q) => $q->where('event_type', $request->event_type))
            ->orderBy('occurred_at', 'desc')
            ->paginate($request->input('per_page', 50));

        return response()->json([
            'data'      => $events->items(),
            'course_id' => $courseId,
            'user_id'   => $userId,
            'meta'      => [
                'total'        => $events->total(),
                'per_page'     => $events->perPage(),
                'current_page' => $events->currentPage(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // LEARNER DASHBOARD ENDPOINTS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/engagement/my-dashboard
     * Full engagement summary for the authenticated learner.
     */
    public function myDashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Latest score per course (most recent week)
        $latestScores = EngagementScore::where('learner_id', $userId)
            ->with('course:id,name')
            ->orderBy('week_number', 'desc')
            ->get()
            ->groupBy('course_id')
            ->map(fn($g) => $g->first())
            ->values();

        // Best active streak across all courses
        $streak = LearningStreak::where('learner_id', $userId)
            ->orderByDesc('current_streak_days')
            ->first();

        // Login stats (last 30 days)
        $loginCount = LearnerLoginSession::where('user_id', $userId)
            ->where('started_at', '>=', now()->subDays(30))
            ->count();

        $lastLogin = LearnerLoginSession::where('user_id', $userId)
            ->orderByDesc('started_at')
            ->first();

        // Events in last 7 days
        $eventsCount = LearnerActivityEvent::where('user_id', $userId)
            ->where('occurred_at', '>=', now()->subDays(7))
            ->count();

        // Weekly trend — last 10 weeks across all courses (aggregate avg)
        $weeklyTrend = EngagementScore::where('learner_id', $userId)
            ->orderByDesc('week_number')
            ->take(60)
            ->get()
            ->groupBy('week_number')
            ->map(fn($g, $week) => [
                'week'  => $week,
                'score' => round($g->avg('engagement_score'), 1),
            ])
            ->sortKeys()
            ->values()
            ->take(-10)
            ->values();

        // Device breakdown from login sessions
        $deviceBreakdown = LearnerLoginSession::where('user_id', $userId)
            ->where('started_at', '>=', now()->subDays(30))
            ->selectRaw('device_type, COUNT(*) as count')
            ->groupBy('device_type')
            ->pluck('count', 'device_type');

        return response()->json([
            'latest_scores'    => $latestScores,
            'streak'           => $streak,
            'login_count_30d'  => $loginCount,
            'last_login'       => $lastLogin?->started_at,
            'events_count_7d'  => $eventsCount,
            'weekly_trend'     => $weeklyTrend,
            'device_breakdown' => $deviceBreakdown,
        ]);
    }

    /**
     * GET /api/v1/engagement/my-login-history
     * Paginated login session history for the authenticated learner.
     */
    public function myLoginHistory(Request $request): JsonResponse
    {
        $sessions = LearnerLoginSession::where('user_id', $request->user()->id)
            ->orderByDesc('started_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $sessions->items(),
            'meta' => [
                'total'        => $sessions->total(),
                'per_page'     => $sessions->perPage(),
                'current_page' => $sessions->currentPage(),
                'last_page'    => $sessions->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/engagement/my-activity-log
     * Paginated activity event log for the authenticated learner.
     */
    public function myActivityLog(Request $request): JsonResponse
    {
        $query = LearnerActivityEvent::where('user_id', $request->user()->id)
            ->with('course:id,name');

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }
        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $events = $query->orderByDesc('occurred_at')
            ->paginate($request->input('per_page', 30));

        return response()->json([
            'data' => $events->items(),
            'meta' => [
                'total'        => $events->total(),
                'per_page'     => $events->perPage(),
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/engagement/my-recommendations
     * AI-generated recommendation cards for the authenticated learner.
     */
    public function myRecommendations(Request $request): JsonResponse
    {
        $recs = app(EngagementRecommendationService::class)
            ->forLearner($request->user()->id);

        return response()->json(['data' => $recs]);
    }
}
