<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\LessonPage;
use App\Models\LessonPageProgress;
use Illuminate\Support\Facades\Auth;

class LessonController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/lesson-pages
     * List all pages in a lesson activity with user progress.
     */
    public function index(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $pages = LessonPage::where('activity_id', $id)
            ->orderBy('sort_order')
            ->get();

        $user = Auth::user();
        if ($user) {
            $progress = LessonPageProgress::where('user_id', $user->id)
                ->where('activity_id', $id)
                ->pluck('is_viewed', 'lesson_page_id')
                ->toArray();

            $pages = $pages->map(function ($page) use ($progress) {
                $page->is_viewed = $progress[$page->id] ?? false;
                return $page;
            });
        }

        return response()->json(['data' => $pages, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/lesson-pages/{id}/viewed
     * Mark a lesson page as viewed by the current user.
     */
    public function markViewed(string $id): JsonResponse
    {
        $page = LessonPage::findOrFail($id);
        $user = Auth::user();

        $progress = LessonPageProgress::firstOrCreate(
            ['user_id' => $user->id, 'lesson_page_id' => $page->id],
            [
                'id' => Str::uuid()->toString(),
                'activity_id' => $page->activity_id,
                'is_viewed' => true,
                'viewed_at' => now(),
            ]
        );

        if (! $progress->is_viewed) {
            $progress->update(['is_viewed' => true, 'viewed_at' => now()]);
        }

        // Check if all pages are viewed; if so, mark activity complete
        $totalPages = LessonPage::where('activity_id', $page->activity_id)->count();
        $viewedPages = LessonPageProgress::where('user_id', $user->id)
            ->where('activity_id', $page->activity_id)
            ->where('is_viewed', true)
            ->count();

        $allViewed = $viewedPages >= $totalPages && $totalPages > 0;

        return response()->json([
            'message' => 'Page marked as viewed.',
            'is_viewed' => true,
            'all_pages_viewed' => $allViewed,
            'viewed_count' => $viewedPages,
            'total_pages' => $totalPages,
        ]);
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
