<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\Section;
use App\Models\Enrollment;
use App\Models\UserActivityCompletion;
use App\Models\Notification;

class SectionController extends Controller
{
    /**
     * GET /api/v1/courses/{id}/sections
     * Return all sections for a course ordered by sort_order.
     */
    public function index(string $id): JsonResponse
    {
        $course   = Course::findOrFail($id);
        $sections = Section::where('course_id', $id)
            ->with('activities')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $sections, 'course_id' => $id]);
    }

    /**
     * GET /api/v1/courses/{id}/sections (public)
     * Return sections for enrolled students, instructors, and admins.
     */
    public function indexPublic(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $course = Course::findOrFail($id);

        // Check access via RolePolicy (handles admin, instructor, student)
        if (!\App\Policies\RolePolicy::canAccessCourse($user, $course)) {
            return response()->json(['message' => 'Access denied. Enroll to view course content.'], 403);
        }

        // Instructors and admins see everything; students see only visible items
        $isAdminOrInstructor = \App\Policies\RolePolicy::isAdminOrInstructor($user);

        $sections = Section::where('course_id', $id)
            ->when(!$isAdminOrInstructor, function ($q) {
                $q->where('visible', true);
            })
            ->with(['activities' => function($q) use ($isAdminOrInstructor) {
                if (!$isAdminOrInstructor) {
                    $q->where('visible', true);
                }
                $q->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        // Inject per-user completion_status into each activity
        if ($user) {
            $completedIds = UserActivityCompletion::where('user_id', $user->id)
                ->where('course_id', $id)
                ->pluck('activity_id')
                ->flip(); // keyed by activity_id for O(1) lookup

            $sections->each(function ($section) use ($completedIds) {
                $section->activities->each(function ($activity) use ($completedIds) {
                    $activity->completion_status = $completedIds->has($activity->id)
                        ? 'completed'
                        : 'available';
                });
            });
        }

        return response()->json(['data' => $sections, 'course_id' => $id]);
    }

    /**
     * POST /api/v1/courses/{id}/sections
     * Add a new week or topic section to the course.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'      => 'required|string|max:255',
            'summary'    => 'sometimes|nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
            'visible'    => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $course = Course::findOrFail($id);

        $maxOrder = Section::where('course_id', $id)->max('sort_order') ?? -1;

        $section = Section::create([
            'id'         => Str::uuid()->toString(),
            'course_id'  => $id,
            'title'      => $request->title,
            'summary'    => $request->input('summary'),
            'sort_order' => $request->input('sort_order', $maxOrder + 1),
            'visible'    => $request->input('visible', true),
        ]);

        $section->load('activities');

        // Notify enrolled students about new section
        $enrolledStudents = Enrollment::where('course_id', $id)
            ->where('role', 'student')
            ->pluck('user_id');

        foreach ($enrolledStudents as $studentId) {
            Notification::create([
                'user_id' => $studentId,
                'type'    => 'course_update',
                'channel' => 'in_app',
                'title'   => 'New Section Added',
                'body'    => "A new section '{$request->title}' has been added to your course.",
                'payload' => ['course_id' => $id, 'section_id' => $section->id],
                'status'  => 'pending',
            ]);
        }

        return response()->json(['message' => 'Section created.', 'data' => $section], 201);
    }

    /**
     * PUT /api/v1/courses/{id}/sections/{sectionId}
     * Rename, reorder, or toggle visibility of a section.
     */
    public function update(Request $request, string $id, string $sectionId): JsonResponse
    {
        $section = Section::where('course_id', $id)->where('id', $sectionId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title'      => 'sometimes|string|max:255',
            'summary'    => 'sometimes|nullable|string',
            'sort_order' => 'sometimes|integer|min:0',
            'visible'    => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $section->update($request->only(['title', 'summary', 'sort_order', 'visible']));
        $section->load('activities');

        return response()->json(['message' => 'Section updated.', 'data' => $section]);
    }

    /**
     * DELETE /api/v1/courses/{id}/sections/{sectionId}
     * Remove a section and cascade-delete all its activities.
     */
    public function destroy(string $id, string $sectionId): JsonResponse
    {
        $section = Section::where('course_id', $id)->where('id', $sectionId)->firstOrFail();
        $section->delete();

        return response()->json(['message' => 'Section deleted.']);
    }
}
