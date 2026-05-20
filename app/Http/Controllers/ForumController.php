<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\ForumDiscussion;
use App\Models\ForumPost;
use App\Services\EngagementComputationService;
use Illuminate\Support\Facades\Log;

class ForumController extends Controller
{
    public function __construct(private EngagementComputationService $engagement) {}

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
            'depth_level'   => 0,
        ]);

        try {
            $this->engagement->logEvent(
                userId:       $user->id,
                eventType:    'forum_post',
                courseId:     $activity->course_id,
                resourceType: 'forum_discussion',
                resourceId:   $discussion->id,
                metadata:     ['depth_level' => 0, 'word_count' => str_word_count($request->content)],
                loginSessionId: $request->input('login_session_id'),
            );
        } catch (\Throwable $e) {
            Log::warning('Engagement: failed to log forum_post', ['discussion' => $discussion->id, 'error' => $e->getMessage()]);
        }

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

        $parentId  = $request->input('parent_id');
        $depthLevel = 1;
        if ($parentId) {
            $parent = ForumPost::find($parentId);
            $depthLevel = $parent ? (($parent->depth_level ?? 0) + 1) : 1;
        }

        $post = ForumPost::create([
            'id'            => Str::uuid()->toString(),
            'discussion_id' => $id,
            'user_id'       => $request->user()->id,
            'parent_id'     => $parentId,
            'content'       => $request->content,
            'depth_level'   => $depthLevel,
        ]);

        $discussion->increment('post_count');

        try {
            $this->engagement->logEvent(
                userId:       $request->user()->id,
                eventType:    'forum_reply',
                courseId:     $discussion->course_id,
                resourceType: 'forum_discussion',
                resourceId:   $id,
                metadata:     ['post_id' => $post->id, 'depth_level' => $depthLevel, 'word_count' => str_word_count($request->content)],
                loginSessionId: $request->input('login_session_id'),
            );
        } catch (\Throwable $e) {
            Log::warning('Engagement: failed to log forum_reply', ['discussion' => $id, 'error' => $e->getMessage()]);
        }

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
