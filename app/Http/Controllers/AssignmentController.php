<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\AssignmentSubmission;
use App\Models\CognitiveSignal;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EngagementComputationService;
use App\Services\GradeService;
use App\Traits\TimeEnforcementHelper;
use Illuminate\Support\Facades\Log;

class AssignmentController extends Controller
{
    use TimeEnforcementHelper;

    public function __construct(
        private EngagementComputationService $engagement,
        private GradeService $grades,
    ) {}

    /**
     * Whether this assignment is configured as online-text-only (the only mode
     * a per-attempt countdown applies to).
     */
    private function isTextOnly(array $settings): bool
    {
        return (bool) ($settings['textOnlineEnabled'] ?? false)
            && ! (bool) ($settings['fileSubmissionEnabled'] ?? true);
    }

    /**
     * POST /api/v1/activities/{id}/assignment-start  (student)
     * For a timed, text-only assignment this records the authoritative clock
     * start (an in-progress draft) and returns the deadline payload. For any
     * other assignment it is a no-op that returns a null deadline.
     */
    public function start(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        $user     = $request->user();
        $settings = is_array($activity->settings) ? $activity->settings : [];
        $limit    = $this->resolveActivityTimeLimit($activity);

        if (! $this->isTextOnly($settings) || ! $limit) {
            return response()->json(array_merge(['timed' => false], $this->deadlinePayload(null)));
        }

        // Reuse an existing in-progress draft so refreshing doesn't reset the clock.
        $draft = AssignmentSubmission::where('activity_id', $id)
            ->where('student_id', $user->id)
            ->where('status', 'in_progress')
            ->orderByDesc('started_at')
            ->first();

        if (! $draft) {
            $draft = AssignmentSubmission::create([
                'id'          => Str::uuid()->toString(),
                'activity_id' => $id,
                'student_id'  => $user->id,
                'course_id'   => $activity->course_id,
                'status'      => 'in_progress',
                'started_at'  => now(),
            ]);
        }

        $deadline = $this->deadlineFromStart($draft->started_at, $limit);

        return response()->json(array_merge(
            ['timed' => true, 'time_limit_minutes' => $limit],
            $this->deadlinePayload($deadline),
        ));
    }

    /**
     * GET /api/v1/activities/{id}/submissions
     * List all submissions for an assignment activity.
     */
    public function index(string $id): JsonResponse
    {
        $activity    = Activity::findOrFail($id);
        $submissions = AssignmentSubmission::where('activity_id', $id)
            ->with(['student', 'grader'])
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(function ($submission) {
                return [
                    'id' => $submission->id,
                    'activity_id' => $submission->activity_id,
                    'student_id' => $submission->student_id,
                    'student_name' => $submission->student ? $submission->student->name : 'Unknown',
                    'course_id' => $submission->course_id,
                    'status' => $submission->status,
                    'submission_text' => $submission->submission_text,
                    'file_path' => $submission->file_path,
                    'file_name' => $submission->file_name,
                    'file_size' => $submission->file_size,
                    'submitted_at' => $submission->submitted_at,
                    'grade' => $submission->grade,
                    'feedback' => $submission->feedback,
                    'graded_by' => $submission->graded_by,
                    'graded_at' => $submission->graded_at,
                    'attempt_number' => $submission->attempt_number,
                    'late' => $submission->late,
                ];
            });

        return response()->json(['data' => $submissions, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/submissions
     * Student submits work for an assignment.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'submission_text' => 'sometimes|nullable|string',
            'file'            => 'sometimes|nullable|file|max:51200', // 50 MB max
            'file_path'       => 'sometimes|nullable|string',
            'file_name'       => 'sometimes|nullable|string|max:255',
            'file_size'       => 'sometimes|nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity = Activity::findOrFail($id);
        $user     = $request->user();

        $hasText = !empty($request->input('submission_text'));
        $hasFile = $request->hasFile('file') || !empty($request->input('file_path'));

        // Enforce the instructor's configured submission modality.
        $settings    = is_array($activity->settings) ? $activity->settings : [];
        $textEnabled = (bool) ($settings['textOnlineEnabled'] ?? false);
        $fileEnabled = (bool) ($settings['fileSubmissionEnabled'] ?? true);

        if ($hasText && !$textEnabled) {
            return response()->json(['errors' => ['submission_text' => 'Text submissions are not allowed for this assignment.']], 422);
        }
        if ($hasFile && !$fileEnabled) {
            return response()->json(['errors' => ['file' => 'File submissions are not allowed for this assignment.']], 422);
        }
        if (!$hasText && !$hasFile) {
            $allowed = [];
            if ($textEnabled) $allowed[] = 'text';
            if ($fileEnabled) $allowed[] = 'a file';
            $hint = $allowed ? implode(' or ', $allowed) : 'a submission';
            return response()->json(['errors' => ['submission' => "Please provide {$hint}."]], 422);
        }

        // Tag the submission with the student's group (if any) for group tasks.
        $enrollment = Enrollment::where('course_id', $activity->course_id)
            ->where('user_id', $user->id)
            ->first();
        $groupName = ($enrollment && is_array($enrollment->groups) && count($enrollment->groups))
            ? $enrollment->groups[0]
            : null;

        $lastAttempt = AssignmentSubmission::where('activity_id', $id)
            ->where('student_id', $user->id)
            ->where('status', '!=', 'in_progress')
            ->max('attempt_number') ?? 0;

        // Timed text-only assignment: resolve the per-attempt deadline from the
        // in-progress draft's authoritative start time, and flag late/auto-submit.
        $limit       = $this->resolveActivityTimeLimit($activity);
        $timedDraft  = ($this->isTextOnly($settings) && $limit)
            ? AssignmentSubmission::where('activity_id', $id)
                ->where('student_id', $user->id)
                ->where('status', 'in_progress')
                ->orderByDesc('started_at')
                ->first()
            : null;
        $deadline      = $timedDraft ? $this->deadlineFromStart($timedDraft->started_at, $limit) : null;
        $autoSubmitted = (bool) $request->boolean('auto_submitted');
        $pastDeadline  = $this->isPastDeadline($deadline);

        $filePath = $request->input('file_path');
        $fileName = $request->input('file_name');
        $fileSize = $request->input('file_size');

        // Handle uploaded file
        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $storedPath = $uploaded->store("submissions/{$activity->course_id}/{$id}", 'public');
            $filePath = $storedPath;
            $fileName = $uploaded->getClientOriginalName();
            $fileSize = $uploaded->getSize();
        }

        $late = ($activity->due_date && now()->greaterThan($activity->due_date)) || $pastDeadline;

        $attrs = [
            'activity_id'     => $id,
            'student_id'      => $user->id,
            'course_id'       => $activity->course_id,
            'group_name'      => $groupName,
            'status'          => 'submitted',
            'submission_text' => $request->input('submission_text'),
            'file_path'       => $filePath,
            'file_name'       => $fileName,
            'file_size'       => $fileSize,
            'submitted_at'    => now(),
            'attempt_number'  => $lastAttempt + 1,
            'late'            => $late,
            'auto_submitted'  => $autoSubmitted || $pastDeadline,
        ];

        // Promote the timed in-progress draft (keeping its started_at) instead of
        // leaving an orphan row; otherwise create a fresh submission.
        if ($timedDraft) {
            $timedDraft->update($attrs);
            $submission = $timedDraft;
        } else {
            $submission = AssignmentSubmission::create(array_merge(['id' => Str::uuid()->toString()], $attrs));
        }

        // Engagement: log submission event and track revision count
        try {
            $this->engagement->logEvent(
                userId:       $user->id,
                eventType:    'activity_complete',
                courseId:     $activity->course_id,
                resourceType: 'activity',
                resourceId:   $id,
                metadata:     [
                    'submission_id' => $submission->id,
                    'attempt_number' => $lastAttempt + 1,
                    'late' => $submission->late,
                ],
                loginSessionId: $request->input('login_session_id'),
            );

            // revision_count = how many prior attempts existed before this one
            CognitiveSignal::where('learner_id', $user->id)
                ->where('course_id', $activity->course_id)
                ->orderBy('week_number', 'desc')
                ->limit(1)
                ->update(['assignment_revision_count' => $lastAttempt]);
        } catch (\Throwable $e) {
            Log::warning('Engagement: failed to log assignment submission', ['activity' => $id, 'error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Submission created.', 'data' => $submission], 201);
    }

    /**
     * GET /api/v1/submissions/{id}
     * Show a single submission.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $submission = AssignmentSubmission::with(['student', 'grader'])->findOrFail($id);
        $user = $request->user();

        // Check if user is the student who submitted, or an instructor/admin for the course
        $isOwner = $submission->student_id === $user->id;
        $isInstructor = $user->hasRole('instructor') || $user->hasRole('admin');
        
        if (!$isOwner && !$isInstructor) {
            return response()->json(['message' => 'Forbidden. You can only view your own submissions.'], 403);
        }

        return response()->json([
            'data' => [
                'id' => $submission->id,
                'activity_id' => $submission->activity_id,
                'student_id' => $submission->student_id,
                'student_name' => $submission->student ? $submission->student->name : 'Unknown',
                'course_id' => $submission->course_id,
                'status' => $submission->status,
                'submission_text' => $submission->submission_text,
                'file_path' => $submission->file_path,
                'file_name' => $submission->file_name,
                'file_size' => $submission->file_size,
                'submitted_at' => $submission->submitted_at,
                'grade' => $submission->grade,
                'feedback' => $submission->feedback,
                'graded_by' => $submission->graded_by,
                'graded_at' => $submission->graded_at,
                'attempt_number' => $submission->attempt_number,
                'late' => $submission->late,
            ]
        ]);
    }

    /**
     * PUT /api/v1/submissions/{id}/grade
     * Instructor grades a submission.
     */
    public function grade(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        // Only instructors and admins can grade
        if (!($user->hasRole('instructor') || $user->hasRole('admin'))) {
            return response()->json(['message' => 'Forbidden. Only instructors and admins can grade submissions.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'grade'    => 'required|numeric|min:0',
            'feedback' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $submission = AssignmentSubmission::findOrFail($id);
        $submission->update([
            'grade'     => $request->grade,
            'feedback'  => $request->input('feedback'),
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
            'status'    => 'graded',
        ]);

        // Mirror the grade into the course grade book so the student sees it via my-grades.
        try {
            $activity = Activity::find($submission->activity_id);
            $student  = User::find($submission->student_id);
            if ($activity && $student) {
                $gradeItem = $this->grades->gradeItemForActivity($activity);
                $this->grades->recordGrade($gradeItem, $student, (float) $request->grade, $request->input('feedback'));
            }
        } catch (\Throwable $e) {
            Log::warning('Grade book: failed to mirror assignment grade', ['submission' => $id, 'error' => $e->getMessage()]);
        }

        return response()->json(['message' => 'Submission graded.', 'data' => $submission]);
    }

    /**
     * GET /api/v1/my-submissions
     * Get all submissions for the current student.
     */
    public function mySubmissions(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AssignmentSubmission::where('student_id', $user->id)
            ->with(['activity', 'course'])
            ->orderBy('submitted_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $submissions = $query->get()->map(function ($submission) {
            return [
                'id' => $submission->id,
                'activity_id' => $submission->activity_id,
                'activity_title' => $submission->activity?->title ?? 'Unknown',
                'course_id' => $submission->course_id,
                'course_name' => $submission->course?->name ?? 'Unknown',
                'status' => $submission->status,
                'submission_text' => $submission->submission_text,
                'file_name' => $submission->file_name,
                'file_url' => $submission->file_path ? asset('storage/' . $submission->file_path) : null,
                'attempt_number' => $submission->attempt_number,
                'submitted_at' => $submission->submitted_at,
                'grade' => $submission->grade,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at,
                'late' => $submission->late,
            ];
        });

        return response()->json(['data' => $submissions]);
    }

    /**
     * GET /api/v1/my-group-works
     * For the authenticated student, return — per course where the student belongs to a group —
     * the assignment tasks together with every group member's submission for each task.
     */
    public function myGroupWorks(Request $request): JsonResponse
    {
        $user = $request->user();

        // Courses where the student belongs to at least one group.
        $enrollments = Enrollment::where('user_id', $user->id)
            ->whereNotNull('groups')
            ->with('course')
            ->get()
            ->filter(fn ($e) => is_array($e->groups) && count($e->groups) > 0);

        $result = [];

        foreach ($enrollments as $enrollment) {
            $groups = array_values($enrollment->groups);

            // Assignment-type tasks in this course.
            $activities = Activity::where('course_id', $enrollment->course_id)
                ->where('type', 'assignment')
                ->where('visible', true)
                ->orderBy('sort_order')
                ->get();

            if ($activities->isEmpty()) {
                continue;
            }

            $tasks = $activities->map(function ($activity) use ($user, $groups) {
                // All submissions made by members of the student's group(s) for this task.
                $groupSubmissions = AssignmentSubmission::where('activity_id', $activity->id)
                    ->whereIn('group_name', $groups)
                    ->with('student')
                    ->orderBy('submitted_at', 'desc')
                    ->get();

                $mine = $groupSubmissions->firstWhere('student_id', $user->id);

                return [
                    'activity_id' => $activity->id,
                    'title'       => $activity->name,
                    'due_date'    => $activity->due_date,
                    'my_status'   => $mine?->status ?? 'not_submitted',
                    'my_grade'    => $mine?->grade,
                    'submissions' => $groupSubmissions->map(fn ($s) => [
                        'id'           => $s->id,
                        'student_id'   => $s->student_id,
                        'student_name' => $s->student?->name ?? 'Unknown',
                        'group_name'   => $s->group_name,
                        'status'       => $s->status,
                        'grade'        => $s->grade,
                        'file_name'    => $s->file_name,
                        'file_url'     => $s->file_path ? asset('storage/' . $s->file_path) : null,
                        'submitted_at' => $s->submitted_at,
                        'is_mine'      => $s->student_id === $user->id,
                    ])->values(),
                ];
            })->values();

            $result[] = [
                'course_id'   => $enrollment->course_id,
                'course_name' => $enrollment->course?->name ?? 'Unknown',
                'group_name'  => implode(', ', $groups),
                'tasks'       => $tasks,
            ];
        }

        return response()->json(['data' => $result]);
    }
}
