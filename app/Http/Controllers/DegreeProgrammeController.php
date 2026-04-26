<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\DegreeProgramme;
use App\Models\User;
use App\Policies\RolePolicy;

class DegreeProgrammeController extends Controller
{
    /**
     * GET /api/v1/degree-programmes
     * List degree programmes with role-based filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = DegreeProgramme::with('college');

        // Apply role-based filtering only if authenticated
        if ($user !== null) {
            if (RolePolicy::isInstructor($user)) {
                $assignedIds = RolePolicy::getAssignedProgrammeIds($user);
                if (!empty($assignedIds)) {
                    $query->whereIn('id', $assignedIds);
                } else {
                    // Instructor with no assignments sees nothing
                    return response()->json(['data' => []]);
                }
            }
        }

        if ($request->filled('college_id')) {
            $query->where('college_id', $request->college_id);
        }

        $programmes = $query->get();
        return response()->json(['data' => $programmes]);
    }

    /**
     * POST /api/v1/degree-programmes
     * Create a new degree programme (Admin only).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only admin can create degree programmes
        if (!RolePolicy::canManageDegreeProgrammes($user)) {
            return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:degree_programmes',
            'college_id' => 'required|string|exists:colleges,id',
            'description' => 'sometimes|nullable|string',
            'duration_years' => 'sometimes|integer|min:1|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $programme = DegreeProgramme::create([
            'id' => Str::uuid()->toString(),
            'name' => $request->name,
            'code' => $request->code,
            'college_id' => $request->college_id,
            'description' => $request->input('description'),
            'duration_years' => $request->input('duration_years', 4),
        ]);

        $programme->load('college');

        return response()->json(['message' => 'Degree programme created.', 'data' => $programme], 201);
    }

    /**
     * GET /api/v1/degree-programmes/{id}
     * View a specific degree programme with role-based access.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $programme = DegreeProgramme::with(['college', 'students', 'courses', 'instructors'])->findOrFail($id);

        // Check access permissions
        if (RolePolicy::isInstructor($user) && !RolePolicy::canAccessProgramme($user, $id)) {
            return response()->json(['message' => 'Forbidden. You do not have access to this degree programme.'], 403);
        }

        return response()->json(['data' => $programme]);
    }

    /**
     * PUT /api/v1/degree-programmes/{id}
     * Update a degree programme (Admin only).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Only admin can update degree programmes
        if (!RolePolicy::canManageDegreeProgrammes($user)) {
            return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
        }

        $programme = DegreeProgramme::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:20|unique:degree_programmes,code,' . $id . ',id',
            'college_id' => 'sometimes|string|exists:colleges,id',
            'description' => 'sometimes|nullable|string',
            'duration_years' => 'sometimes|integer|min:1|max:7',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $programme->update($request->only(['name', 'code', 'college_id', 'description', 'duration_years']));
        $programme->load('college');

        return response()->json(['message' => 'Degree programme updated.', 'data' => $programme]);
    }

    /**
     * DELETE /api/v1/degree-programmes/{id}
     * Delete a degree programme (Admin only).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Only admin can delete degree programmes
        if (!RolePolicy::canManageDegreeProgrammes($user)) {
            return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
        }

        $programme = DegreeProgramme::findOrFail($id);
        $programme->delete();

        return response()->json(['message' => 'Degree programme deleted.']);
    }

    /**
     * POST /api/v1/degree-programmes/{id}/instructors
     * Assign instructors to a degree programme (Admin only).
     */
    public function assignInstructors(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        // Only admin can assign instructors
        if (!RolePolicy::canManageDegreeProgrammes($user)) {
            return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
        }

        $programme = DegreeProgramme::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'instructor_ids' => 'required|array',
            'instructor_ids.*' => 'string|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate all are instructors
        $instructors = User::whereIn('id', $request->instructor_ids)
            ->where('role', 'instructor')
            ->pluck('id')
            ->toArray();

        if (count($instructors) !== count($request->instructor_ids)) {
            return response()->json(['message' => 'All assigned users must be instructors.'], 422);
        }

        $programme->instructors()->sync($instructors);

        return response()->json([
            'message' => 'Instructors assigned successfully.',
            'data' => $programme->instructors()->get(),
        ]);
    }

    /**
     * GET /api/v1/degree-programmes/{id}/students
     * View students within a degree programme.
     */
    public function students(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $programme = DegreeProgramme::findOrFail($id);

        // Check access permissions (same logic as show() method)
        if (RolePolicy::isInstructor($user) && !RolePolicy::canAccessProgramme($user, $id)) {
            return response()->json(['message' => 'Forbidden. You do not have access to this degree programme.'], 403);
        }

        $students = $programme->students()->with('degreeProgramme')->get();

        return response()->json(['data' => $students]);
    }

    /**
     * GET /api/v1/degree-programmes/{id}/courses
     * View courses associated with a degree programme.
     */
    public function courses(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $programme = DegreeProgramme::findOrFail($id);

        // Check access permissions (same logic as show() method)
        if (RolePolicy::isInstructor($user) && !RolePolicy::canAccessProgramme($user, $id)) {
            return response()->json(['message' => 'Forbidden. You do not have access to this degree programme.'], 403);
        }

        $courses = $programme->courses()->with('instructor')->get();

        return response()->json(['data' => $courses]);
    }
}
