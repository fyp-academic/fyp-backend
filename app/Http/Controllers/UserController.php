<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Instructor;
use App\Policies\RolePolicy;

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
     * Get a specific instructor with full profile.
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
            ->findOrFail($id);

        return response()->json(['data' => $instructor]);
    }
}
