<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\BookChapter;

class BookController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/chapters
     * List all chapters of a book activity.
     */
    public function index(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $chapters = BookChapter::where('activity_id', $id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $chapters, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/chapters
     * Add a new chapter to a book.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'content'     => 'required|string',
            'sub_chapter' => 'sometimes|boolean',
            'hidden'      => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $maxOrder = BookChapter::where('activity_id', $id)->max('sort_order') ?? -1;

        $chapter = BookChapter::create([
            'id'          => Str::uuid()->toString(),
            'activity_id' => $id,
            'title'       => $request->title,
            'content'     => $request->content,
            'sort_order'  => $request->input('sort_order', $maxOrder + 1),
            'sub_chapter' => $request->input('sub_chapter', false),
            'hidden'      => $request->input('hidden', false),
        ]);

        return response()->json(['message' => 'Chapter created.', 'data' => $chapter], 201);
    }

    /**
     * PUT /api/v1/chapters/{id}
     * Update a chapter's content.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $chapter = BookChapter::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'       => 'sometimes|string|max:255',
            'content'     => 'sometimes|string',
            'sub_chapter' => 'sometimes|boolean',
            'hidden'      => 'sometimes|boolean',
            'sort_order'  => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chapter->update($request->only(['title', 'content', 'sub_chapter', 'hidden', 'sort_order']));

        return response()->json(['message' => 'Chapter updated.', 'data' => $chapter]);
    }

    /**
     * DELETE /api/v1/chapters/{id}
     * Remove a chapter.
     */
    public function destroy(string $id): JsonResponse
    {
        $chapter = BookChapter::findOrFail($id);
        $chapter->delete();

        return response()->json(['message' => 'Chapter deleted.']);
    }
}
