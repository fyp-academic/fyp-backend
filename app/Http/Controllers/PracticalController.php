<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\PracticalSubmission;
use App\Services\ActivityResultService;
use App\Services\EngagementComputationService;
use App\Traits\TimeEnforcementHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Practical Problem activity — instructor posts a sample web page (HTML/CSS/JS)
 * + instructions; students imitate it in an in-browser editor with a live preview.
 * Student work is autosaved (draft) and submitted; instructors review and grade it
 * through the shared ActivityResultService (same path as quiz/SCORM/H5P).
 */
class PracticalController extends Controller
{
    use TimeEnforcementHelper;

    public function __construct(private EngagementComputationService $engagement) {}

    private function emptyFiles(): array
    {
        return ['html' => '', 'css' => '', 'js' => ''];
    }

    private function normalizeFiles($files): array
    {
        $files = is_array($files) ? $files : [];
        return [
            'html' => (string) ($files['html'] ?? ''),
            'css'  => (string) ($files['css'] ?? ''),
            'js'   => (string) ($files['js'] ?? ''),
        ];
    }

    /**
     * GET /api/v1/activities/{id}/practical-template  (public — enrolled students)
     * Returns the instructor's sample + instructions stored in activity.settings.
     */
    public function showTemplate(string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        $settings = $activity->settings ?? [];

        return response()->json([
            'activity_id'  => $id,
            'name'         => $activity->name,
            'instructions' => $settings['instructions'] ?? ($activity->description ?? ''),
            'sample'       => $this->normalizeFiles($settings['sample'] ?? []),
            'starter'      => $this->normalizeFiles($settings['starter'] ?? []),
            'grade_max'    => $activity->grade_max,
        ]);
    }

    /**
     * GET /api/v1/activities/{id}/practical-submission  (student)
     * The current student's own draft/submission for this activity. Read-only:
     * merely viewing a practical (e.g. the lesson page auto-opening it) must NOT
     * create a row or start the clock — a timed attempt's `started_at` is anchored
     * only by an explicit start() call. The countdown is reported only once the
     * student has actually begun (an existing row carries `started_at`).
     */
    public function mySubmission(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        $user     = $request->user();
        $limit    = $this->resolveActivityTimeLimit($activity);

        $sub = PracticalSubmission::where('activity_id', $id)
            ->where('student_id', $user->id)
            ->first();

        $deadline = ($limit && $sub?->started_at)
            ? $this->deadlineFromStart($sub->started_at, $limit)
            : null;

        return response()->json(array_merge(
            ['data' => $sub, 'time_limit_minutes' => $limit],
            $this->deadlinePayload($deadline),
        ));
    }

    /**
     * POST /api/v1/activities/{id}/practical-start  (student)
     * Explicit, idempotent start of a practical attempt. This is the only place
     * that anchors the authoritative clock (`started_at`) — the first time the
     * student chooses to begin — so passively opening the activity never starts the
     * countdown. Refresh-safe: an existing `started_at` is preserved.
     */
    public function start(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        $user     = $request->user();
        $limit    = $this->resolveActivityTimeLimit($activity);

        $sub = PracticalSubmission::firstOrNew([
            'activity_id' => $id,
            'student_id'  => $user->id,
        ]);
        if (! $sub->exists) {
            $sub->id        = (string) Str::uuid();
            $sub->course_id = $activity->course_id;
            $sub->files     = $this->emptyFiles();
            $sub->status    = 'draft';
        }
        if (! $sub->started_at) {
            $sub->started_at = now();
        }
        $sub->save();

        $deadline = $this->deadlineFromStart($sub->started_at, $limit);

        return response()->json(array_merge(
            ['data' => $sub, 'time_limit_minutes' => $limit],
            $this->deadlinePayload($deadline),
        ));
    }

    /**
     * POST /api/v1/activities/{id}/practical-submission  (student)
     * Upsert the student's code. status=draft (autosave) or submitted (final).
     */
    public function save(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'files'      => 'required|array',
            'files.html' => 'sometimes|nullable|string',
            'files.css'  => 'sometimes|nullable|string',
            'files.js'   => 'sometimes|nullable|string',
            'status'     => 'sometimes|in:draft,submitted',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity = Activity::findOrFail($id);
        $user     = $request->user();
        $status   = $request->input('status', 'draft');

        $sub = PracticalSubmission::firstOrNew([
            'activity_id' => $id,
            'student_id'  => $user->id,
        ]);
        if (! $sub->exists) {
            $sub->id = (string) Str::uuid();
        }
        $sub->course_id = $activity->course_id;
        $sub->files     = $this->normalizeFiles($request->input('files'));
        $sub->status    = $status;
        // Record the clock start the first time we see this submission (e.g. if
        // the editor saved before mySubmission ran), so timing is consistent.
        if (! $sub->started_at) {
            $sub->started_at = now();
        }
        if ($status === 'submitted') {
            $sub->submitted_at = now();
            // Flag late/auto submissions for a timed activity.
            $limit    = $this->resolveActivityTimeLimit($activity);
            $deadline = $this->deadlineFromStart($sub->started_at, $limit);
            if ($this->isPastDeadline($deadline)) {
                $sub->auto_submitted = true;
            }
        }
        $sub->save();

        // Submitting marks the activity complete for progress/gradebook plumbing.
        if ($status === 'submitted') {
            try {
                app(ActivityResultService::class)->recordCompletion($activity, $user->id);
                $this->engagement->logEvent(
                    userId:       $user->id,
                    eventType:    'practical_submit',
                    courseId:     $activity->course_id,
                    resourceType: 'activity',
                    resourceId:   $activity->id,
                    metadata:     ['activity_type' => 'practical'],
                    loginSessionId: $request->input('login_session_id'),
                );
            } catch (\Throwable $e) {
                Log::warning('Practical: submit side-effects failed', ['activity' => $id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json(['message' => 'Saved.', 'data' => $sub], $sub->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * GET /api/v1/activities/{id}/practical-submissions  (instructor)
     * All student submissions for review.
     */
    public function submissions(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $subs = PracticalSubmission::where('activity_id', $id)
            ->with('student:id,name,email')
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $subs, 'activity_id' => $id]);
    }

    /**
     * GET /api/v1/courses/{id}/practical-submissions  (instructor)
     * Every practical submission in the course in one query — a course-level
     * source so the instructor view doesn't depend on enumerating each activity
     * (which can miss submissions whose activity_id is stale/outside the section
     * traversal). Mirrors the student Grade Book, which reads submissions directly.
     */
    public function courseSubmissions(string $id): JsonResponse
    {
        // Match by the authoritative activity→course link (activities table) OR the
        // submission's own course_id column, so a row whose course_id was recorded
        // wrong (e.g. set from a proctoring session) is still found as long as its
        // activity belongs to this course.
        $activityIds = Activity::where('course_id', $id)->pluck('id');

        $subs = PracticalSubmission::where(fn ($q) => $q
                ->whereIn('activity_id', $activityIds)
                ->orWhere('course_id', $id))
            ->with(['student:id,name,email', 'activity:id,name,grade_max'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $subs, 'course_id' => $id]);
    }

    /**
     * GET /api/v1/practical-submissions/{id}  (instructor)
     */
    public function submission(string $id): JsonResponse
    {
        $sub = PracticalSubmission::with('student:id,name,email')->findOrFail($id);
        return response()->json(['data' => $sub]);
    }

    /**
     * POST /api/v1/practical-submissions/{id}/grade  (instructor)
     */
    public function grade(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'grade'    => 'required|numeric|min:0',
            'feedback' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sub      = PracticalSubmission::findOrFail($id);
        $activity = Activity::findOrFail($sub->activity_id);
        $gradeMax = (float) ($activity->grade_max ?? 100);

        $sub->grade     = (float) $request->grade;
        $sub->feedback  = $request->input('feedback');
        $sub->graded_by = $request->user()->id;
        $sub->graded_at = now();
        $sub->save();

        app(ActivityResultService::class)->recordScore($activity, $sub->student_id, (float) $request->grade, $gradeMax > 0 ? $gradeMax : 100);

        return response()->json(['message' => 'Graded.', 'data' => $sub]);
    }
}
