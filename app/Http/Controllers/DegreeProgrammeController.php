<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\DegreeProgramme;
use App\Models\User;

class DegreeProgrammeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DegreeProgramme::with('college');

        if ($request->filled('college_id')) {
            $query->where('college_id', $request->college_id);
        }

        $programmes = $query->get();
        return response()->json(['data' => $programmes]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:degree_programmes',
            'college_id' => 'required|string|exists:colleges,id',
            'description' => 'sometimes|nullable|string',
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
        ]);

        $programme->load('college');

        return response()->json(['message' => 'Degree programme created.', 'data' => $programme], 201);
    }

    public function show(string $id): JsonResponse
    {
        $programme = DegreeProgramme::with(['college', 'students', 'courses', 'instructors'])->findOrFail($id);
        return response()->json(['data' => $programme]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $programme = DegreeProgramme::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:20|unique:degree_programmes,code,' . $id . ',id',
            'college_id' => 'sometimes|string|exists:colleges,id',
            'description' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $programme->update($request->only(['name', 'code', 'college_id', 'description']));
        $programme->load('college');

        return response()->json(['message' => 'Degree programme updated.', 'data' => $programme]);
    }

    public function destroy(string $id): JsonResponse
    {
        $programme = DegreeProgramme::findOrFail($id);
        $programme->delete();

        return response()->json(['message' => 'Degree programme deleted.']);
    }

    /**
     * POST /api/v1/degree-programmes/{id}/instructors
     * Assign instructors to a degree programme.
     */
    public function assignInstructors(Request $request, string $id): JsonResponse
    {
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
    public function students(string $id): JsonResponse
    {
        $programme = DegreeProgramme::findOrFail($id);
        $students = $programme->students()->get();

        return response()->json(['data' => $students]);
    }

    /**
     * GET /api/v1/degree-programmes/{id}/courses
     * View courses associated with a degree programme.
     */
    public function courses(string $id): JsonResponse
    {
        $programme = DegreeProgramme::findOrFail($id);
        $courses = $programme->courses()->with('instructor')->get();

        return response()->json(['data' => $courses]);
    }
}
