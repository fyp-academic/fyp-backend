<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Instructor;
use App\Policies\RolePolicy;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * GET /api/v1/users
     * Return users with role-based filtering.
     * - Admin: Can see all users
     * - Instructor: Can only see students in their assigned programmes
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Start building query
        $query = User::select(
            'id', 'name', 'email', 'role', 'registration_number',
            'degree_programme_id', 'year_of_study', 'education_level',
            'nationality', 'department', 'institution', 'email_verified_at', 'created_at'
        )->with('degreeProgramme.college');

        // Apply role-based filtering
        if (RolePolicy::isAdmin($user)) {
            // Admin can see all users, optionally filter by role
            if ($request->filled('role')) {
                $query->where('role', $request->role);
            }
        } elseif (RolePolicy::isInstructor($user)) {
            // Instructors can only see students in their assigned programmes
            $assignedProgrammeIds = RolePolicy::getAssignedProgrammeIds($user);

            if (empty($assignedProgrammeIds)) {
                return response()->json(['data' => []]);
            }

            $query->where('role', 'student')
                ->whereIn('degree_programme_id', $assignedProgrammeIds);

            // Additional filters for instructors
            if ($request->filled('degree_programme_id')) {
                // Validate instructor has access to this programme
                if (!in_array($request->degree_programme_id, $assignedProgrammeIds)) {
                    return response()->json(['message' => 'Forbidden. You do not have access to this programme.'], 403);
                }
                $query->where('degree_programme_id', $request->degree_programme_id);
            }

            if ($request->filled('year_of_study')) {
                $query->where('year_of_study', $request->year_of_study);
            }
        } else {
            // Students cannot view users list
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Apply common filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('registration_number', 'ilike', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $users]);
    }

    /**
     * GET /api/v1/users/{id}
     * Get a specific user with role-based access control.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $targetUser = User::with('degreeProgramme.college')->findOrFail($id);

        // Check access permissions
        if (RolePolicy::isAdmin($user)) {
            // Admin can view any user
            return response()->json(['data' => $targetUser]);
        }

        if (RolePolicy::isInstructor($user)) {
            // Instructors can view students in their assigned programmes
            if ($targetUser->role !== 'student') {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            $assignedProgrammeIds = RolePolicy::getAssignedProgrammeIds($user);
            if (!in_array($targetUser->degree_programme_id, $assignedProgrammeIds)) {
                return response()->json(['message' => 'Forbidden. This student is not in your assigned programmes.'], 403);
            }

            return response()->json(['data' => $targetUser]);
        }

        // Students can only view themselves
        if ($user->id === $targetUser->id) {
            return response()->json(['data' => $targetUser]);
        }

        return response()->json(['message' => 'Forbidden.'], 403);
    }

    /**
     * GET /api/v1/instructors
     * Return instructors with their profile data.
     * - Admin: Can see all instructors
     * - Instructor/Student: Cannot view instructors list
     */
    public function instructors(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only admins can view instructors list
        if (!RolePolicy::isAdmin($user)) {
            return response()->json(['message' => 'Forbidden. Only admins can view instructors.'], 403);
        }

        $query = Instructor::with(['user', 'college', 'degreeProgrammes'])
            ->whereHas('user', function ($q) {
                $q->where('role', 'instructor');
            });

        // Filter by college
        if ($request->filled('college_id')) {
            $query->where('college_id', $request->college_id);
        }

        // Filter by academic rank
        if ($request->filled('academic_rank')) {
            $query->where('academic_rank', $request->academic_rank);
        }

        // Filter by employment type
        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->employment_type);
        }

        // Search by name, email, or staff_id
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'ilike', "%{$search}%")
                  ->orWhere('staff_id', 'ilike', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('email', 'ilike', "%{$search}%");
                  });
            });
        }

        $instructors = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $instructors]);
    }

    /**
     * GET /api/v1/instructors/{id}
     * Get a specific instructor with full profile (by user_id).
     * - Admin: Can view any instructor
     */
    public function showInstructor(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Only admins can view instructor details
        if (!RolePolicy::isAdmin($user)) {
            return response()->json(['message' => 'Forbidden. Only admins can view instructor details.'], 403);
        }

        $instructor = Instructor::with(['user', 'college', 'degreeProgrammes.courses', 'courses'])
            ->where('user_id', $id)
            ->firstOrFail();

        return response()->json(['data' => $instructor]);
    }

    /**
     * PUT /api/v1/users/{id}
     * Update a student user.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Only admins can update users
        if (!RolePolicy::isAdmin($user)) {
            return response()->json(['message' => 'Forbidden. Only admins can update users.'], 403);
        }

        $targetUser = User::findOrFail($id);

        // Convert empty strings to null for proper validation
        $input = $request->all();
        foreach ($input as $key => $value) {
            if ($value === '') {
                $input[$key] = null;
            }
        }
        $request->replace($input);

        // Validate based on role
        if ($targetUser->role === 'student') {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'registration_number' => 'sometimes|nullable|string|max:30|unique:users,registration_number,' . $id,
                'degree_programme_id' => 'sometimes|nullable|string|exists:degree_programmes,id',
                'gender' => 'sometimes|nullable|string|in:male,female,other',
                'phone_number' => 'sometimes|nullable|string|max:30',
                'year_of_study' => 'sometimes|nullable|integer|min:1|max:7',
                'education_level' => 'sometimes|nullable|string|in:certificate,diploma,bachelor,master,phd',
                'nationality' => 'sometimes|nullable|string|max:50',
            ]);
        } else {
            // For non-students, basic fields only
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
            ]);
        }

        $targetUser->update($validated);

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $targetUser->fresh()->load('degreeProgramme.college'),
        ]);
    }

    /**
     * PUT /api/v1/instructors/{id}
     * Update an instructor profile (by user_id).
     */
    public function updateInstructor(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Only admins can update instructors
        if (!RolePolicy::isAdmin($user)) {
            return response()->json(['message' => 'Forbidden. Only admins can update instructors.'], 403);
        }

        $instructor = Instructor::where('user_id', $id)->firstOrFail();
        $targetUser = $instructor->user;

        // Convert empty strings to null for proper validation
        $input = $request->all();
        foreach ($input as $key => $value) {
            if ($value === '') {
                $input[$key] = null;
            }
        }
        $request->replace($input);

        $validated = $request->validate([
            // User fields
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $targetUser->id,
            'gender' => 'sometimes|nullable|string|in:male,female,other',
            'phone_number' => 'sometimes|nullable|string|max:20',
            // Instructor fields
            'staff_id' => 'sometimes|nullable|string|max:30|unique:instructors,staff_id,' . $instructor->id,
            'college_id' => 'sometimes|nullable|string|exists:colleges,id',
            'national_id' => 'sometimes|nullable|string|max:50',
            'employment_type' => 'sometimes|nullable|string|in:full-time,part-time,visiting',
            'academic_rank' => 'sometimes|nullable|string|in:assistant_lecturer,lecturer,senior_lecturer,associate_professor,professor,tutorial_assistant,graduate_assistant',
            'date_of_employment' => 'sometimes|nullable|date',
            'highest_qualification' => 'sometimes|nullable|string|max:100',
            'field_of_specialization' => 'sometimes|nullable|string|max:100',
            'awarding_institution' => 'sometimes|nullable|string|max:100',
            'year_of_graduation' => 'sometimes|nullable|integer|min:1900|max:' . (date('Y') + 1),
            'bio' => 'sometimes|nullable|string|max:1000',
            'office_location' => 'sometimes|nullable|string|max:100',
            'office_hours' => 'sometimes|nullable|string|max:100',
            'assigned_programme_ids' => 'sometimes|array',
            'assigned_programme_ids.*' => 'string|exists:degree_programmes,id',
        ]);

        // Update user fields
        $userFields = array_intersect_key($validated, array_flip(['name', 'email', 'gender', 'phone_number']));
        if (!empty($userFields)) {
            $targetUser->update($userFields);
        }

        // Update instructor fields
        $instructorFields = array_diff_key($validated, array_flip(['name', 'email', 'gender', 'phone_number', 'assigned_programme_ids']));
        if (!empty($instructorFields)) {
            $instructor->update($instructorFields);
        }

        // Update assigned programmes if provided
        if (isset($validated['assigned_programme_ids'])) {
            $instructor->degreeProgrammes()->sync($validated['assigned_programme_ids']);
        }

        return response()->json([
            'message' => 'Instructor updated successfully.',
            'data' => $instructor->fresh()->load(['user', 'college', 'degreeProgrammes']),
        ]);
    }

    /**
     * DELETE /api/v1/users/{id}
     * Delete a user.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Only admins can delete users
        if (!RolePolicy::isAdmin($user)) {
            return response()->json(['message' => 'Forbidden. Only admins can delete users.'], 403);
        }

        // Prevent self-deletion
        if ($user->id === $id) {
            return response()->json(['message' => 'Cannot delete your own account.'], 422);
        }

        $targetUser = User::findOrFail($id);

        // Delete associated instructor profile if exists
        if ($targetUser->role === 'instructor' && $targetUser->instructor) {
            $targetUser->instructor->degreeProgrammes()->detach();
            $targetUser->instructor->delete();
        }

        $targetUser->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * DELETE /api/v1/instructors/{id}
     * Delete an instructor (user + profile) by user_id.
     */
    public function destroyInstructor(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Only admins can delete instructors
        if (!RolePolicy::isAdmin($user)) {
            return response()->json(['message' => 'Forbidden. Only admins can delete instructors.'], 403);
        }

        // Resolve by the user record (source of truth). The instructor profile is
        // optional — some instructor users may lack a row in the instructors table.
        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json(['message' => 'Instructor not found.'], 404);
        }

        // Prevent self-deletion
        if ($targetUser->id === $user->id) {
            return response()->json(['message' => 'Cannot delete your own account.'], 422);
        }

        DB::transaction(function () use ($id, $targetUser) {
            $instructor = Instructor::where('user_id', $id)->first();
            if ($instructor) {
                $instructor->degreeProgrammes()->detach();
                $instructor->delete();
            }

            // Deleting the user cascades any remaining instructor profile and
            // programme pivots, and nulls course instructor_id (per FK rules).
            $targetUser->delete();
        });

        return response()->json(['message' => 'Instructor deleted successfully.']);
    }
}
