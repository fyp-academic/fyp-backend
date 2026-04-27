<?php

namespace App\Http\Controllers;

use App\Models\ChatReport;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChatReportController extends Controller
{
    /**
     * List all chat reports with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Only admin and instructors can view reports
        if (!$user->isAdmin() && !$user->isInstructor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = ChatReport::with([
            'reporter:id,name,email',
            'reportedUser:id,name,email',
            'conversation:id,title,type',
            'message:id,content',
            'resolver:id,name'
        ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by reported user
        if ($request->has('reported_user_id')) {
            $query->where('reported_user_id', $request->reported_user_id);
        }

        // Filter by reporter
        if ($request->has('reporter_id')) {
            $query->where('reporter_id', $request->reporter_id);
        }

        // Filter by conversation
        if ($request->has('conversation_id')) {
            $query->where('conversation_id', $request->conversation_id);
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->to_date);
        }

        // Search by reason or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reason', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $reports = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $reports->items(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ]
        ]);
    }

    /**
     * Get detailed information about a specific report.
     */
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isAdmin() && !$user->isInstructor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $report = ChatReport::with([
            'reporter:id,name,email,profile_image_url',
            'reportedUser:id,name,email,profile_image_url',
            'conversation:id,title,type,course_id,programme_id,is_locked,is_moderated',
            'conversation.course:id,title',
            'conversation.degreeProgramme:id,name',
            'message:id,content,sender_id,created_at,deleted_at',
            'message.sender:id,name',
            'resolver:id,name'
        ])->findOrFail($id);

        // Get message context (messages before and after)
        $contextMessages = [];
        if ($report->message_id && $report->conversation_id) {
            $contextMessages = Message::where('conversation_id', $report->conversation_id)
                ->where('created_at', '>=', $report->message->created_at->subMinutes(30))
                ->where('created_at', '<=', $report->message->created_at->addMinutes(30))
                ->with('sender:id,name')
                ->orderBy('created_at')
                ->limit(20)
                ->get();
        }

        // Get reported user's recent reports count
        $recentReportsCount = ChatReport::where('reported_user_id', $report->reported_user_id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // Get conversation participants
        $participants = ConversationParticipant::where('conversation_id', $report->conversation_id)
            ->with('user:id,name,email')
            ->get();

        return response()->json([
            'report' => $report,
            'context_messages' => $contextMessages,
            'reported_user_stats' => [
                'recent_reports_count' => $recentReportsCount,
                'total_reports_count' => ChatReport::where('reported_user_id', $report->reported_user_id)->count(),
            ],
            'participants' => $participants,
        ]);
    }

    /**
     * Create a new report.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'reported_user_id' => 'required|string|exists:users,id',
            'conversation_id' => 'required|string|exists:conversations,id',
            'message_id' => 'nullable|string|exists:messages,id',
            'reason' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        // Verify user is part of the conversation
        $isParticipant = ConversationParticipant::where('conversation_id', $validated['conversation_id'])
            ->where('user_id', $user->id)
            ->exists();

        if (!$isParticipant) {
            return response()->json(['error' => 'You are not a participant in this conversation'], 403);
        }

        // Check if message belongs to the conversation
        if (!empty($validated['message_id'])) {
            $message = Message::where('id', $validated['message_id'])
                ->where('conversation_id', $validated['conversation_id'])
                ->first();

            if (!$message) {
                return response()->json(['error' => 'Message not found in this conversation'], 404);
            }
        }

        // Check for duplicate reports within 24 hours
        $existingReport = ChatReport::where('reporter_id', $user->id)
            ->where('reported_user_id', $validated['reported_user_id'])
            ->where('conversation_id', $validated['conversation_id'])
            ->where('created_at', '>=', now()->subHours(24))
            ->first();

        if ($existingReport) {
            return response()->json([
                'error' => 'You have already reported this user in this conversation within the last 24 hours',
                'existing_report_id' => $existingReport->id
            ], 409);
        }

        $report = ChatReport::create([
            'id' => (string) Str::uuid(),
            'reporter_id' => $user->id,
            'reported_user_id' => $validated['reported_user_id'],
            'conversation_id' => $validated['conversation_id'],
            'message_id' => $validated['message_id'] ?? null,
            'reason' => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
        ]);

        // Notify admins (optional - can be implemented with notifications)

        return response()->json([
            'message' => 'Report submitted successfully',
            'data' => $report->load(['reporter:id,name', 'reportedUser:id,name', 'conversation:id,title'])
        ], 201);
    }

    /**
     * Resolve a report.
     */
    public function resolve(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isAdmin() && !$user->isInstructor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:resolved,dismissed',
            'resolution_notes' => 'nullable|string|max:2000',
            'action_taken' => 'nullable|string|in:none,warn,block_user,lock_conversation',
        ]);

        $report = ChatReport::findOrFail($id);

        if ($report->status !== 'pending') {
            return response()->json(['error' => 'Report has already been resolved'], 400);
        }

        DB::beginTransaction();
        try {
            // Update report status
            $report->update([
                'status' => $validated['status'],
                'resolution_notes' => $validated['resolution_notes'] ?? null,
                'resolved_by' => $user->id,
                'resolved_at' => now(),
            ]);

            // Take action if specified
            if (!empty($validated['action_taken'])) {
                match ($validated['action_taken']) {
                    'block_user' => $this->blockUserFromConversation($report->reported_user_id, $report->conversation_id, $user->id),
                    'lock_conversation' => $this->lockConversation($report->conversation_id, $user->id),
                    default => null,
                };
            }

            DB::commit();

            return response()->json([
                'message' => 'Report resolved successfully',
                'data' => $report->fresh(['resolver:id,name'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to resolve report: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Block a user from a conversation.
     */
    public function blockUser(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isAdmin() && !$user->isInstructor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|string|exists:users,id',
            'conversation_id' => 'required|string|exists:conversations,id',
            'reason' => 'nullable|string|max:500',
        ]);

        return $this->blockUserFromConversation(
            $validated['user_id'],
            $validated['conversation_id'],
            $user->id,
            $validated['reason'] ?? null
        );
    }

    /**
     * Unblock a user from a conversation.
     */
    public function unblockUser(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isAdmin() && !$user->isInstructor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|string|exists:users,id',
            'conversation_id' => 'required|string|exists:conversations,id',
        ]);

        $participant = ConversationParticipant::where('conversation_id', $validated['conversation_id'])
            ->where('user_id', $validated['user_id'])
            ->first();

        if (!$participant) {
            return response()->json(['error' => 'User is not a participant in this conversation'], 404);
        }

        if (!$participant->is_blocked) {
            return response()->json(['error' => 'User is not blocked from this conversation'], 400);
        }

        $participant->update([
            'is_blocked' => false,
            'blocked_at' => null,
            'blocked_by' => null,
        ]);

        return response()->json([
            'message' => 'User unblocked successfully',
            'data' => $participant->fresh(['user:id,name', 'blocker:id,name'])
        ]);
    }

    /**
     * Lock or unlock a conversation.
     */
    public function toggleConversationLock(string $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isAdmin() && !$user->isInstructor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $conversation = Conversation::findOrFail($id);

        $conversation->update([
            'is_locked' => !$conversation->is_locked,
        ]);

        $action = $conversation->is_locked ? 'locked' : 'unlocked';

        return response()->json([
            'message' => "Conversation {$action} successfully",
            'data' => [
                'conversation_id' => $conversation->id,
                'is_locked' => $conversation->is_locked,
            ]
        ]);
    }

    /**
     * Get chat moderation statistics.
     */
    public function statistics(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isAdmin() && !$user->isInstructor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'reports' => [
                'total' => ChatReport::count(),
                'pending' => ChatReport::where('status', 'pending')->count(),
                'resolved' => ChatReport::where('status', 'resolved')->count(),
                'dismissed' => ChatReport::where('status', 'dismissed')->count(),
                'today' => ChatReport::whereDate('created_at', today())->count(),
                'this_week' => ChatReport::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            ],
            'conversations' => [
                'total' => Conversation::count(),
                'locked' => Conversation::where('is_locked', true)->count(),
                'moderated' => Conversation::where('is_moderated', true)->count(),
                'course_chats' => Conversation::where('type', 'course')->count(),
                'programme_chats' => Conversation::where('type', 'programme')->count(),
                'direct_chats' => Conversation::where('type', 'direct')->count(),
            ],
            'messages' => [
                'total' => Message::count(),
                'today' => Message::whereDate('created_at', today())->count(),
                'deleted' => Message::whereNotNull('deleted_at')->count(),
                'pinned' => Message::where('is_pinned', true)->count(),
            ],
            'blocked_users' => ConversationParticipant::where('is_blocked', true)->count(),
        ];

        // Top reported users
        $topReportedUsers = ChatReport::select('reported_user_id', DB::raw('count(*) as report_count'))
            ->with('reportedUser:id,name,email')
            ->groupBy('reported_user_id')
            ->orderBy('report_count', 'desc')
            ->limit(10)
            ->get();

        // Recent reports
        $recentReports = ChatReport::with([
            'reporter:id,name',
            'reportedUser:id,name',
            'conversation:id,title'
        ])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'statistics' => $stats,
            'top_reported_users' => $topReportedUsers,
            'recent_reports' => $recentReports,
        ]);
    }

    /**
     * List all conversations with moderation info.
     */
    public function conversations(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isAdmin() && !$user->isInstructor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Conversation::with([
            'owner:id,name',
            'participant:id,name',
            'course:id,title',
            'degreeProgramme:id,name',
        ])->withCount([
            'messages',
            'messages as deleted_messages_count' => function ($q) {
                $q->whereNotNull('deleted_at');
            },
            'participants',
        ]);

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by lock status
        if ($request->has('is_locked')) {
            $query->where('is_locked', $request->boolean('is_locked'));
        }

        // Filter by moderation status
        if ($request->has('is_moderated')) {
            $query->where('is_moderated', $request->boolean('is_moderated'));
        }

        // Search by title
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('owner', function ($oq) use ($search) {
                      $oq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('participant', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $conversations = $query->orderBy('updated_at', 'desc')
            ->paginate($request->per_page ?? 20);

        // Add report counts for each conversation
        $conversationIds = collect($conversations->items())->pluck('id');
        $reportCounts = ChatReport::whereIn('conversation_id', $conversationIds)
            ->select('conversation_id', DB::raw('count(*) as count'))
            ->groupBy('conversation_id')
            ->pluck('count', 'conversation_id');

        $items = collect($conversations->items())->map(function ($conv) use ($reportCounts) {
            $conv->reports_count = $reportCounts[$conv->id] ?? 0;
            return $conv;
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ]
        ]);
    }

    /**
     * Get blocked participants list.
     */
    public function blockedUsers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isAdmin() && !$user->isInstructor()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = ConversationParticipant::where('is_blocked', true)
            ->with([
                'user:id,name,email',
                'conversation:id,title,type',
                'blocker:id,name',
            ]);

        // Filter by conversation
        if ($request->has('conversation_id')) {
            $query->where('conversation_id', $request->conversation_id);
        }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $blocked = $query->orderBy('blocked_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $blocked->items(),
            'meta' => [
                'current_page' => $blocked->currentPage(),
                'last_page' => $blocked->lastPage(),
                'per_page' => $blocked->perPage(),
                'total' => $blocked->total(),
            ]
        ]);
    }

    /**
     * Helper method to block a user from a conversation.
     */
    private function blockUserFromConversation(string $userId, string $conversationId, string $blockedBy, ?string $reason = null): JsonResponse
    {
        $participant = ConversationParticipant::firstOrCreate(
            [
                'conversation_id' => $conversationId,
                'user_id' => $userId,
            ],
            [
                'id' => (string) Str::uuid(),
                'role' => 'member',
                'joined_at' => now(),
            ]
        );

        if ($participant->is_blocked) {
            return response()->json(['error' => 'User is already blocked from this conversation'], 409);
        }

        $participant->update([
            'is_blocked' => true,
            'blocked_at' => now(),
            'blocked_by' => $blockedBy,
        ]);

        return response()->json([
            'message' => 'User blocked from conversation successfully',
            'data' => $participant->fresh(['user:id,name', 'blocker:id,name'])
        ]);
    }

    /**
     * Helper method to lock a conversation.
     */
    private function lockConversation(string $conversationId, string $lockedBy): void
    {
        $conversation = Conversation::findOrFail($conversationId);
        $conversation->update([
            'is_locked' => true,
            'is_moderated' => true,
        ]);
    }
}
