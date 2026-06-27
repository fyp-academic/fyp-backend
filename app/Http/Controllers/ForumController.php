<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\ForumDiscussion;
use App\Models\ForumPost;
use App\Models\ForumPostReaction;
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

        // Anonymity is driven by the parent discussion-activity's settings:
        //   full   → every reply is forced anonymous
        //   partial→ the student may choose (request `anonymous`)
        //   off    → never anonymous
        $anonMode = $this->anonymousMode($discussion);
        $anonymous = $anonMode === 'full'
            ? true
            : ($anonMode === 'partial' ? (bool) $request->input('anonymous', false) : false);

        $post = ForumPost::create([
            'id'            => Str::uuid()->toString(),
            'discussion_id' => $id,
            'user_id'       => $request->user()->id,
            'parent_id'     => $parentId,
            'content'       => $request->content,
            'depth_level'   => $depthLevel,
            'anonymous'     => $anonymous,
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

    // ── Reactions ───────────────────────────────────────────────────────

    /**
     * POST /api/v1/posts/{id}/react   body: { value: 1 | -1 }
     * Like (1) / dislike (-1) a post. Re-sending the same value clears it (toggle).
     * Recomputes the denormalized likes_count / dislikes_count cache.
     */
    public function react(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'value' => 'required|integer|in:1,-1',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $post   = ForumPost::findOrFail($id);
        $userId = $request->user()->id;
        $value  = (int) $request->input('value');

        $existing = ForumPostReaction::where('post_id', $id)->where('user_id', $userId)->first();

        if ($existing && $existing->value === $value) {
            $existing->delete();            // toggle off
            $mine = 0;
        } else {
            ForumPostReaction::updateOrCreate(
                ['post_id' => $id, 'user_id' => $userId],
                ['id' => $existing->id ?? Str::uuid()->toString(), 'value' => $value]
            );
            $mine = $value;
        }

        $post->likes_count    = ForumPostReaction::where('post_id', $id)->where('value', 1)->count();
        $post->dislikes_count = ForumPostReaction::where('post_id', $id)->where('value', -1)->count();
        $post->save();

        return response()->json([
            'likes_count'    => $post->likes_count,
            'dislikes_count' => $post->dislikes_count,
            'my_reaction'    => $mine,
        ]);
    }

    // ── Discussion activity (single-topic) ──────────────────────────────

    /**
     * GET /api/v1/activities/{id}/discussion
     * Returns the single topic + replies for a `discussion` activity, applying
     * the activity's anonymity, sort and "must post before viewing" options.
     * The topic is lazily created from the activity on first access.
     */
    public function discussionForActivity(Request $request, string $id): JsonResponse
    {
        $activity   = Activity::findOrFail($id);
        $settings   = $activity->settings ?? [];
        $discussion = $this->ensureTopic($activity);
        $userId     = optional($request->user())->id;

        $topicPost = ForumPost::where('discussion_id', $discussion->id)
            ->where('depth_level', 0)->with('user:id,name,profile_image')->first();

        $sortDir = ($settings['default_sort'] ?? 'oldest') === 'newest' ? 'desc' : 'asc';
        $replies = ForumPost::where('discussion_id', $discussion->id)
            ->where('depth_level', '>', 0)
            ->with('user:id,name,profile_image')
            ->orderBy('created_at', $sortDir)
            ->get();

        // "Participants must respond before viewing other replies"
        $hasPosted = $userId
            ? $replies->contains(fn ($p) => $p->user_id === $userId)
            : false;
        $gated = (bool) ($settings['require_post_before_view'] ?? false);
        $instructorId = $activity->course?->instructor_id;
        $locked = $gated && ! $hasPosted && $userId !== $instructorId;

        $reactionMap = $this->reactionMap($replies->pluck('id')->all(), $userId);

        $payload = [
            'activity_id'   => $id,
            'discussion_id' => $discussion->id,
            'title'         => $activity->name,
            'content'       => $topicPost?->content ?? ($activity->description ?? ''),
            'options'       => [
                'anonymous_mode'       => $this->anonymousMode($discussion),
                'disallow_threaded'    => (bool) ($settings['disallow_threaded'] ?? false),
                'allow_liking'         => (bool) ($settings['allow_liking'] ?? true),
                'graded'               => (bool) ($settings['graded'] ?? false),
                'default_thread_state' => $settings['default_thread_state'] ?? 'expanded',
                'lock_thread_state'    => (bool) ($settings['lock_thread_state'] ?? false),
                'default_sort'         => $settings['default_sort'] ?? 'oldest',
                'lock_sort'            => (bool) ($settings['lock_sort'] ?? false),
                'require_post_before_view' => $gated,
            ],
            'reply_count'   => $replies->count(),
            'has_posted'    => $hasPosted,
            'locked'        => $locked,
            'replies'       => $locked ? [] : $replies->map(fn ($p) => $this->serializePost($p, $reactionMap, $userId))->values(),
        ];

        return response()->json($payload);
    }

    /**
     * Lazily create the single ForumDiscussion topic for a discussion activity.
     */
    private function ensureTopic(Activity $activity): ForumDiscussion
    {
        $discussion = ForumDiscussion::where('activity_id', $activity->id)->first();
        if ($discussion) {
            return $discussion;
        }

        $discussion = ForumDiscussion::create([
            'id'          => Str::uuid()->toString(),
            'activity_id' => $activity->id,
            'course_id'   => $activity->course_id,
            'user_id'     => $activity->course?->instructor_id,
            'title'       => $activity->name,
            'pinned'      => false,
            'locked'      => false,
            'post_count'  => 1,
        ]);

        ForumPost::create([
            'id'            => Str::uuid()->toString(),
            'discussion_id' => $discussion->id,
            'user_id'       => $discussion->user_id,
            'subject'       => $activity->name,
            'content'       => $activity->description ?? '',
            'depth_level'   => 0,
        ]);

        return $discussion;
    }

    private function anonymousMode(ForumDiscussion $discussion): string
    {
        $settings = optional($discussion->activity)->settings ?? [];
        $mode = $settings['anonymous_mode'] ?? 'off';
        return in_array($mode, ['off', 'partial', 'full'], true) ? $mode : 'off';
    }

    /**
     * Map of post_id => ['likes'=>n,'dislikes'=>n,'mine'=>1|-1|0] for the viewer.
     */
    private function reactionMap(array $postIds, ?string $userId): array
    {
        if (empty($postIds)) {
            return [];
        }
        $mine = [];
        if ($userId) {
            $mine = ForumPostReaction::whereIn('post_id', $postIds)
                ->where('user_id', $userId)
                ->pluck('value', 'post_id')->all();
        }
        return ['mine' => $mine];
    }

    private function serializePost(ForumPost $post, array $reactionMap, ?string $userId): array
    {
        $anonymous = (bool) $post->anonymous;
        return [
            'id'             => $post->id,
            'parent_id'      => $post->parent_id,
            'content'        => $post->content,
            'depth_level'    => $post->depth_level,
            'created_at'     => $post->created_at,
            'likes_count'    => (int) $post->likes_count,
            'dislikes_count' => (int) $post->dislikes_count,
            'my_reaction'    => (int) (($reactionMap['mine'] ?? [])[$post->id] ?? 0),
            'anonymous'      => $anonymous,
            'author'         => $anonymous
                ? ['id' => null, 'name' => 'Anonymous', 'avatar' => null]
                : ['id' => $post->user?->id, 'name' => $post->user?->name, 'avatar' => $post->user?->profile_image ?? null],
            'is_mine'        => $userId !== null && $post->user_id === $userId,
        ];
    }
}
