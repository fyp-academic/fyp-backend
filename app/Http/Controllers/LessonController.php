<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\LessonPage;

class LessonController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/lesson-pages
     * List all pages in a lesson activity.
     */
    public function index(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $pages = LessonPage::where('activity_id', $id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $pages, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/lesson-pages
     * Add a new page to a lesson.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'      => 'required|string|max:255',
            'content'    => 'required|string',
            'page_type'  => 'sometimes|string|in:content,question,branch,end',
            'sort_order' => 'sometimes|integer|min:0',
            'jumps'      => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $maxOrder = LessonPage::where('activity_id', $id)->max('sort_order') ?? -1;

        $page = LessonPage::create([
            'id'          => Str::uuid()->toString(),
            'activity_id' => $id,
            'title'       => $request->title,
            'content'     => $request->content,
            'page_type'   => $request->input('page_type', 'content'),
            'sort_order'  => $request->input('sort_order', $maxOrder + 1),
            'jumps'       => $request->input('jumps'),
        ]);

        return response()->json(['message' => 'Page created.', 'data' => $page], 201);
    }

    /**
     * PUT /api/v1/lesson-pages/{id}
     * Update a lesson page.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $page = LessonPage::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'      => 'sometimes|string|max:255',
            'content'    => 'sometimes|string',
            'page_type'  => 'sometimes|string|in:content,question,branch,end',
            'sort_order' => 'sometimes|integer|min:0',
            'jumps'      => 'sometimes|nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $page->update($request->only(['title', 'content', 'page_type', 'sort_order', 'jumps']));

        return response()->json(['message' => 'Page updated.', 'data' => $page]);
    }

    /**
     * DELETE /api/v1/lesson-pages/{id}
     * Remove a lesson page.
     */
    public function destroy(string $id): JsonResponse
    {
        $page = LessonPage::findOrFail($id);
        $page->delete();

        return response()->json(['message' => 'Page deleted.']);
    }
}
