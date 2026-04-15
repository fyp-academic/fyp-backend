<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\GlossaryEntry;

class GlossaryController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/glossary-entries
     * List all entries in a glossary activity.
     */
    public function index(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $entries = GlossaryEntry::where('activity_id', $id)
            ->with('user')
            ->orderBy('concept')
            ->get();

        return response()->json(['data' => $entries, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/glossary-entries
     * Add a new glossary entry.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'concept'    => 'required|string|max:255',
            'definition' => 'required|string',
            'aliases'    => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);

        $entry = GlossaryEntry::create([
            'id'          => Str::uuid()->toString(),
            'activity_id' => $id,
            'user_id'     => $request->user()->id,
            'concept'     => $request->concept,
            'definition'  => $request->definition,
            'aliases'     => $request->input('aliases'),
            'approved'    => false,
        ]);

        return response()->json(['message' => 'Entry created.', 'data' => $entry], 201);
    }

    /**
     * PUT /api/v1/glossary-entries/{id}
     * Update a glossary entry.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $entry = GlossaryEntry::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'concept'    => 'sometimes|string|max:255',
            'definition' => 'sometimes|string',
            'aliases'    => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $entry->update($request->only(['concept', 'definition', 'aliases']));

        return response()->json(['message' => 'Entry updated.', 'data' => $entry]);
    }

    /**
     * PATCH /api/v1/glossary-entries/{id}/approve
     * Approve a glossary entry.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $entry = GlossaryEntry::findOrFail($id);
        $entry->update([
            'approved'    => true,
            'approved_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Entry approved.', 'data' => $entry]);
    }

    /**
     * DELETE /api/v1/glossary-entries/{id}
     * Remove a glossary entry.
     */
    public function destroy(string $id): JsonResponse
    {
        $entry = GlossaryEntry::findOrFail($id);
        $entry->delete();

        return response()->json(['message' => 'Entry deleted.']);
    }
}
