<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\Section;

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
