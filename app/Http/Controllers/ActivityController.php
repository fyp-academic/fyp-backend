<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Section;
use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\UserActivityCompletion;
use App\Models\Notification;
use App\Services\NotificationService;
use App\Services\EngagementComputationService;
use Illuminate\Support\Facades\Log;

class ActivityController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private EngagementComputationService $engagement,
    ) {}
    /**
     * GET /api/v1/sections/{id}/activities
     * Return all activities within a section ordered by sort_order.
     */
    public function index(string $id): JsonResponse
    {
        $section    = Section::findOrFail($id);
        $activities = Activity::where('section_id', $id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $activities, 'section_id' => $id]);
    }

    /**
     * GET /api/v1/sections/{id}/activities (public)
     * Return activities for enrolled students, instructors, and admins.
     */
    public function indexPublic(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $section = Section::findOrFail($id);
        $course = \App\Models\Course::findOrFail($section->course_id);

        // Check access via RolePolicy (handles admin, instructor, student)
        if (!\App\Policies\RolePolicy::canAccessCourse($user, $course)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        // Instructors and admins see everything; students see only visible items
        $isAdminOrInstructor = \App\Policies\RolePolicy::isAdminOrInstructor($user);

        $activities = Activity::where('section_id', $id)
            ->when(!$isAdminOrInstructor, function ($q) {
                $q->where('visible', true);
            })
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $activities, 'section_id' => $id]);
    }

    /**
     * GET /api/v1/activities/{id} (public)
     * Show a single activity for enrolled students.
     */
    public function showPublic(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $activity = Activity::findOrFail($id);
        $course = \App\Models\Course::findOrFail($activity->course_id);

        // Check if user is enrolled or has course access (instructor/admin)
        $isEnrolled = \App\Models\Enrollment::where('user_id', $user?->id)
            ->where('course_id', $activity->course_id)
            ->exists();

        $canAccess = $isEnrolled || \App\Policies\RolePolicy::canAccessCourse($user, $course);

        if (!$canAccess && $course->visibility !== 'shown') {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        // Instructors and admins can view hidden activities; students cannot
        if (!$activity->visible && !\App\Policies\RolePolicy::canAccessCourse($user, $course)) {
            return response()->json(['message' => 'Activity not available.'], 403);
        }

        // Log content_view engagement event for students
        try {
            if ($user && $isEnrolled) {
                $this->engagement->logEvent(
                    userId:       $user->id,
                    eventType:    'content_view',
                    courseId:     $activity->course_id,
                    resourceType: 'activity',
                    resourceId:   $id,
                    metadata:     ['activity_type' => $activity->type],
                    loginSessionId: $request->input('login_session_id'),
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Engagement: failed to log content_view', ['activity' => $id, 'error' => $e->getMessage()]);
        }

        return response()->json(['data' => $activity]);
    }

    /**
     * POST /api/v1/sections/{id}/activities
     * Add a new activity to a section.
     * Types: quiz | assignment | forum | url | file | h5p | scorm | workshop | label | page
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type'        => ['required', 'string', Rule::in(Activity::TYPES)],
            'name'        => 'required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'visible'     => 'sometimes|boolean',
            'grade_max'   => 'sometimes|nullable|numeric|min:0',
            'due_date'    => 'sometimes|nullable|date',
            'sort_order'  => 'sometimes|integer|min:0',
            'settings'    => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $section  = Section::findOrFail($id);
        $maxOrder = Activity::where('section_id', $id)->max('sort_order') ?? -1;

        $activity = Activity::create([
            'id'                => Str::uuid()->toString(),
            'section_id'        => $id,
            'course_id'         => $section->course_id,
            'type'              => $request->type,
            'name'              => $request->name,
            'description'       => $request->input('description'),
            'due_date'          => $request->input('due_date'),
            'visible'           => $request->input('visible', true),
            'completion_status' => 'none',
            'grade_max'         => $request->input('grade_max'),
            'sort_order'        => $request->input('sort_order', $maxOrder + 1),
            'settings'          => $request->input('settings'),
        ]);

        // Notify enrolled students
        $enrolledStudents = Enrollment::where('course_id', $section->course_id)
            ->pluck('user_id')
            ->toArray();

        foreach ($enrolledStudents as $studentId) {
            $this->notificationService->sendToUser(
                $studentId,
                'course_update',
                'in_app',
                'New ' . ucfirst($request->type) . ' Added',
                "A new {$request->type} '{$request->name}' has been added to your course.",
                ['course_id' => $section->course_id, 'activity_id' => $activity->id, 'activity_type' => $request->type]
            );
        }

        return response()->json(['message' => 'Activity created.', 'data' => $activity], 201);
    }

    /**
     * PUT /api/v1/activities/{id}
     * Edit activity details, due date, visibility, or grade maximum.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'              => 'sometimes|string|max:255',
            'description'       => 'sometimes|nullable|string',
            'visible'           => 'sometimes|boolean',
            'grade_max'         => 'sometimes|nullable|numeric|min:0',
            'due_date'          => 'sometimes|nullable|date',
            'sort_order'        => 'sometimes|integer|min:0',
            'completion_status' => 'sometimes|string|in:completed,incomplete,none',
            'settings'          => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity->update($request->only([
            'name', 'description', 'visible', 'grade_max', 'due_date', 'sort_order', 'completion_status', 'settings',
        ]));

        return response()->json(['message' => 'Activity updated.', 'data' => $activity]);
    }

    /**
     * DELETE /api/v1/activities/{id}
     * Remove an activity; also deletes associated grade items.
     */
    public function destroy(string $id): JsonResponse
    {
        $activity = Activity::findOrFail($id);
        $activity->delete();

        return response()->json(['message' => 'Activity deleted.']);
    }

    /**
     * POST /api/v1/activities/{id}/complete
     * Mark an activity as completed for the authenticated student.
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $activity = Activity::findOrFail($id);
        $course = \App\Models\Course::findOrFail($activity->course_id);

        // Check enrollment
        $enrollment = Enrollment::where('user_id', $user->id)
            ->where('course_id', $activity->course_id)
            ->first();

        if (!$enrollment && !\App\Policies\RolePolicy::canAccessCourse($user, $course)) {
            return response()->json(['message' => 'Access denied.'], 403);
        }

        $completionType = $request->input('completion_type', 'manual');

        // Record per-user completion (upsert — idempotent)
        UserActivityCompletion::updateOrCreate(
            ['user_id' => $user->id, 'activity_id' => $activity->id],
            [
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'course_id'       => $activity->course_id,
                'completion_type' => $completionType,
                'completed_at'    => now(),
            ]
        );

        // Compute per-user progress for this course
        $progress = UserActivityCompletion::progressFor($user->id, $activity->course_id);

        // Persist updated progress on the enrollment record
        if ($enrollment) {
            $enrollment->progress    = $progress;
            $enrollment->last_access = now();
            $enrollment->save();
        }

        // Engagement: log completion event and touch learning streak
        try {
            $this->engagement->logEvent(
                userId:       $user->id,
                eventType:    'activity_complete',
                courseId:     $activity->course_id,
                resourceType: 'activity',
                resourceId:   $id,
                value:        $progress,
                metadata:     ['activity_type' => $activity->type],
                loginSessionId: $request->input('login_session_id'),
            );
        } catch (\Throwable $e) {
            Log::warning('Engagement: failed to log activity_complete', ['activity' => $id, 'error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Activity marked as completed.',
            'progress' => $progress,
        ]);
    }
}
