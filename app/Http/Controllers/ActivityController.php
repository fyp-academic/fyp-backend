<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\Section;
use App\Models\Activity;

class ActivityController extends Controller
{
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
}
