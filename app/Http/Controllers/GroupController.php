<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

/**
 * Group Management Controller
 * Handles group CRUD operations and student assignment within courses
 */
class GroupController extends Controller
{
    /**
     * GET /api/v1/courses/{courseId}/groups
     * List all groups in a course
     */
    public function index(string $courseId): JsonResponse
    {
        $course = Course::findOrFail($courseId);

        // Get all unique groups from enrollments in this course
        $enrollments = Enrollment::where('course_id', $courseId)->get();
        
        $groups = [];
        foreach ($enrollments as $enrollment) {
            if ($enrollment->groups && is_array($enrollment->groups)) {
                foreach ($enrollment->groups as $group) {
                    if (!isset($groups[$group])) {
                        $groups[$group] = [];
                    }
                    $groups[$group][] = [
                        'user_id'     => $enrollment->user_id,
                        'student_name' => $enrollment->user->name ?? 'Unknown',
                        'email'        => $enrollment->user->email ?? '',
                    ];
                }
            }
        }

        $result = [];
        foreach ($groups as $groupName => $members) {
            $result[] = [
                'name'        => $groupName,
                'member_count' => count($members),
                'members'     => $members,
            ];
        }

        return response()->json(['data' => $result, 'course_id' => $courseId]);
    }

    /**
     * POST /api/v1/courses/{courseId}/groups/{groupName}/add-student
     * Add a student to a group
     */
    public function addStudent(Request $request, string $courseId, string $groupName): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $course = Course::findOrFail($courseId);
        $user = User::findOrFail($request->user_id);

        $enrollment = Enrollment::where('course_id', $courseId)
            ->where('user_id', $request->user_id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'User is not enrolled in this course.'], 404);
        }

        // Get current groups or initialize empty array
        $currentGroups = $enrollment->groups ?? [];
        if (!is_array($currentGroups)) {
            $currentGroups = [];
        }

        // Add group if not already member
        if (!in_array($groupName, $currentGroups)) {
            $currentGroups[] = $groupName;
            $enrollment->update(['groups' => $currentGroups]);
        }

        return response()->json([
            'message' => 'Student added to group.',
            'data' => [
                'user_id'  => $enrollment->user_id,
                'groups'   => $currentGroups,
                'group'    => $groupName,
            ],
        ], 201);
    }

    /**
     * DELETE /api/v1/courses/{courseId}/groups/{groupName}/remove-student/{userId}
     * Remove a student from a group
     */
    public function removeStudent(string $courseId, string $groupName, string $userId): JsonResponse
    {
        $course = Course::findOrFail($courseId);

        $enrollment = Enrollment::where('course_id', $courseId)
            ->where('user_id', $userId)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'User is not enrolled in this course.'], 404);
        }

        $currentGroups = $enrollment->groups ?? [];
        if (!is_array($currentGroups)) {
            $currentGroups = [];
        }

        // Remove group
        $currentGroups = array_filter($currentGroups, fn($g) => $g !== $groupName);
        $currentGroups = array_values($currentGroups); // Re-index array

        $enrollment->update(['groups' => $currentGroups]);

        return response()->json(['message' => 'Student removed from group.']);
    }

    /**
     * DELETE /api/v1/courses/{courseId}/groups/{groupName}
     * Delete a group and remove all students from it
     */
    public function destroy(string $courseId, string $groupName): JsonResponse
    {
        $course = Course::findOrFail($courseId);

        // Get all enrollments with this group
        $enrollments = Enrollment::where('course_id', $courseId)->get();

        foreach ($enrollments as $enrollment) {
            $currentGroups = $enrollment->groups ?? [];
            if (!is_array($currentGroups)) {
                $currentGroups = [];
            }

            // Remove the group
            $currentGroups = array_filter($currentGroups, fn($g) => $g !== $groupName);
            $currentGroups = array_values($currentGroups); // Re-index

            $enrollment->update(['groups' => $currentGroups]);
        }

        return response()->json(['message' => 'Group deleted.']);
    }

    /**
     * PUT /api/v1/courses/{courseId}/groups/{groupName}/rename
     * Rename a group
     */
    public function rename(Request $request, string $courseId, string $groupName): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'new_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $course = Course::findOrFail($courseId);
        $newName = $request->new_name;

        // Get all enrollments with the old group name
        $enrollments = Enrollment::where('course_id', $courseId)->get();

        foreach ($enrollments as $enrollment) {
            $currentGroups = $enrollment->groups ?? [];
            if (!is_array($currentGroups)) {
                $currentGroups = [];
            }

            // Rename the group
            $currentGroups = array_map(fn($g) => $g === $groupName ? $newName : $g, $currentGroups);

            $enrollment->update(['groups' => $currentGroups]);
        }

        return response()->json(['message' => 'Group renamed.']);
    }

    /**
     * GET /api/v1/courses/{courseId}/groups/{groupName}
     * Get details of a specific group
     */
    public function show(string $courseId, string $groupName): JsonResponse
    {
        $course = Course::findOrFail($courseId);

        $enrollments = Enrollment::where('course_id', $courseId)->get();
        
        $members = [];
        foreach ($enrollments as $enrollment) {
            if ($enrollment->groups && is_array($enrollment->groups) && in_array($groupName, $enrollment->groups)) {
                $members[] = [
                    'user_id'     => $enrollment->user_id,
                    'student_name' => $enrollment->user->name ?? 'Unknown',
                    'email'        => $enrollment->user->email ?? '',
                    'role'         => $enrollment->role,
                    'progress'     => $enrollment->progress,
                ];
            }
        }

        return response()->json([
            'data' => [
                'name'         => $groupName,
                'course_id'    => $courseId,
                'member_count' => count($members),
                'members'      => $members,
            ],
        ]);
    }
}
