<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\DatabaseField;
use App\Models\DatabaseEntry;

class DatabaseActivityController extends Controller
{
    // ── Fields ──────────────────────────────────────────────────────────

    /**
     * GET /api/v1/activities/{id}/db-fields
     * List all field definitions for a database activity.
     */
    public function fields(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $fields = DatabaseField::where('activity_id', $id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $fields, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/db-fields
     * Add a new field definition.
     */
    public function storeField(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'type'        => 'required|string|in:text,number,date,url,image,file,textarea,checkbox,radio,dropdown,latlong',
            'description' => 'sometimes|nullable|string',
            'required'    => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
            'options'     => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $maxOrder = DatabaseField::where('activity_id', $id)->max('sort_order') ?? -1;

        $field = DatabaseField::create([
            'id'          => Str::uuid()->toString(),
            'activity_id' => $id,
            'name'        => $request->name,
            'type'        => $request->type,
            'description' => $request->input('description'),
            'required'    => $request->input('required', false),
            'sort_order'  => $request->input('sort_order', $maxOrder + 1),
            'options'     => $request->input('options'),
        ]);

        return response()->json(['message' => 'Field created.', 'data' => $field], 201);
    }

    /**
     * DELETE /api/v1/db-fields/{id}
     * Remove a field definition.
     */
    public function destroyField(string $id): JsonResponse
    {
        $field = DatabaseField::findOrFail($id);
        $field->delete();

        return response()->json(['message' => 'Field deleted.']);
    }

    // ── Entries ─────────────────────────────────────────────────────────

    /**
     * GET /api/v1/activities/{id}/db-entries
     * List all entries for a database activity.
     */
    public function entries(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $entries = DatabaseEntry::where('activity_id', $id)
            ->with('student')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $entries, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/db-entries
     * Create a new entry.
     */
    public function storeEntry(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);

        $entry = DatabaseEntry::create([
            'id'          => Str::uuid()->toString(),
            'activity_id' => $id,
            'student_id'  => $request->user()->id,
            'content'     => $request->content,
            'approved'    => false,
        ]);

        return response()->json(['message' => 'Entry created.', 'data' => $entry], 201);
    }

    /**
     * PATCH /api/v1/db-entries/{id}/approve
     * Approve or reject an entry.
     */
    public function approveEntry(Request $request, string $id): JsonResponse
    {
        $entry = DatabaseEntry::findOrFail($id);

        $entry->update([
            'approved'    => true,
            'approved_by' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Entry approved.', 'data' => $entry]);
    }

    /**
     * DELETE /api/v1/db-entries/{id}
     * Remove an entry.
     */
    public function destroyEntry(string $id): JsonResponse
    {
        $entry = DatabaseEntry::findOrFail($id);
        $entry->delete();

        return response()->json(['message' => 'Entry deleted.']);
    }
}
