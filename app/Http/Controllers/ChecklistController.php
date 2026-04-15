<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\ChecklistItem;

class ChecklistController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/checklist-items
     * List all items in a checklist activity.
     */
    public function index(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $items = ChecklistItem::where('activity_id', $id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $items, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/checklist-items
     * Add a new item to a checklist.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text'               => 'required|string|max:500',
            'is_required'        => 'sometimes|boolean',
            'checked_by_default' => 'sometimes|boolean',
            'sort_order'         => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $maxOrder = ChecklistItem::where('activity_id', $id)->max('sort_order') ?? -1;

        $item = ChecklistItem::create([
            'id'                 => Str::uuid()->toString(),
            'activity_id'        => $id,
            'text'               => $request->text,
            'is_required'        => $request->input('is_required', false),
            'checked_by_default' => $request->input('checked_by_default', false),
            'sort_order'         => $request->input('sort_order', $maxOrder + 1),
        ]);

        return response()->json(['message' => 'Checklist item created.', 'data' => $item], 201);
    }

    /**
     * PUT /api/v1/checklist-items/{id}
     * Update a checklist item.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $item = ChecklistItem::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'text'               => 'sometimes|string|max:500',
            'is_required'        => 'sometimes|boolean',
            'checked_by_default' => 'sometimes|boolean',
            'sort_order'         => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $item->update($request->only(['text', 'is_required', 'checked_by_default', 'sort_order']));

        return response()->json(['message' => 'Checklist item updated.', 'data' => $item]);
    }

    /**
     * DELETE /api/v1/checklist-items/{id}
     * Remove a checklist item.
     */
    public function destroy(string $id): JsonResponse
    {
        $item = ChecklistItem::findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Checklist item deleted.']);
    }
}
