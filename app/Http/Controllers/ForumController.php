<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\ForumDiscussion;
use App\Models\ForumPost;

class ForumController extends Controller
{
    // ── Discussions ─────────────────────────────────────────────────────

    /**
     * GET /api/v1/activities/{id}/discussions
     * List all discussions in a forum activity.
     */
    public function discussions(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $discussions = ForumDiscussion::where('activity_id', $id)
            ->with('user')
            ->withCount('posts')
            ->orderByDesc('pinned')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $discussions, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/discussions
     * Start a new discussion thread.
     */
    public function createDiscussion(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'   => 'required|string|max:255',
            'content' => 'required|string',
            'pinned'  => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity = Activity::findOrFail($id);
        $user     = $request->user();

        $discussion = ForumDiscussion::create([
            'id'          => Str::uuid()->toString(),
            'activity_id' => $id,
            'course_id'   => $activity->course_id,
            'user_id'     => $user->id,
            'title'       => $request->title,
            'pinned'      => $request->input('pinned', false),
            'locked'      => false,
            'post_count'  => 1,
        ]);

        ForumPost::create([
            'id'            => Str::uuid()->toString(),
            'discussion_id' => $discussion->id,
            'user_id'       => $user->id,
            'subject'       => $request->title,
            'content'       => $request->content,
        ]);

        return response()->json(['message' => 'Discussion created.', 'data' => $discussion], 201);
    }

    // ── Posts ───────────────────────────────────────────────────────────

    /**
     * GET /api/v1/discussions/{id}/posts
     * List all posts in a discussion.
     */
    public function posts(string $id): JsonResponse
    {
        ForumDiscussion::findOrFail($id);
        $posts = ForumPost::where('discussion_id', $id)
            ->with('user')
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $posts, 'discussion_id' => $id]);
    }

    /**
     * POST /api/v1/discussions/{id}/posts
     * Reply to a discussion.
     */
    public function reply(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'content'   => 'required|string',
            'parent_id' => 'sometimes|nullable|string|exists:forum_posts,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $discussion = ForumDiscussion::findOrFail($id);

        if ($discussion->locked) {
            return response()->json(['message' => 'Discussion is locked.'], 403);
        }

        $post = ForumPost::create([
            'id'            => Str::uuid()->toString(),
            'discussion_id' => $id,
            'user_id'       => $request->user()->id,
            'parent_id'     => $request->input('parent_id'),
            'content'       => $request->content,
        ]);

        $discussion->increment('post_count');

        return response()->json(['message' => 'Reply posted.', 'data' => $post], 201);
    }

    /**
     * PATCH /api/v1/discussions/{id}/lock
     * Lock or unlock a discussion.
     */
    public function toggleLock(Request $request, string $id): JsonResponse
    {
        $discussion = ForumDiscussion::findOrFail($id);
        $discussion->update(['locked' => !$discussion->locked]);

        return response()->json([
            'message' => $discussion->locked ? 'Discussion locked.' : 'Discussion unlocked.',
            'data'    => $discussion,
        ]);
    }

    /**
     * PATCH /api/v1/discussions/{id}/pin
     * Pin or unpin a discussion.
     */
    public function togglePin(Request $request, string $id): JsonResponse
    {
        $discussion = ForumDiscussion::findOrFail($id);
        $discussion->update(['pinned' => !$discussion->pinned]);

        return response()->json([
            'message' => $discussion->pinned ? 'Discussion pinned.' : 'Discussion unpinned.',
            'data'    => $discussion,
        ]);
    }
}
