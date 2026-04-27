<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessagePinned;
use App\Events\MessageSent;
use App\Events\MessageStatusUpdated;
use App\Events\ReactionAdded;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Policies\ChatPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
     * GET /api/v1/conversations/{id}/messages
     * Paginated message history for a conversation.
     */
    public function index(string $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);
        $this->authorizeConversation($conversation);

        $messages = Message::where('conversation_id', $id)
            ->orderBy('timestamp')
            ->get();

        // Mark unread messages from the other participant as read
        Message::where('conversation_id', $id)
            ->where('sender_id', '!=', Auth::id())
            ->where('read', false)
            ->update(['read' => true]);

        $conversation->update(['unread_count' => 0]);

        return response()->json(['data' => $messages]);
    }

    /**
     * POST /api/v1/conversations/{id}/messages
     * Send a new message (text and/or file attachment).
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);
        $this->authorizeConversation($conversation);

        $validator = Validator::make($request->all(), [
            'content'    => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:20480',    // 20 MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // At least one of content or attachment is required
        if (! $request->filled('content') && ! $request->hasFile('attachment')) {
            return response()->json(['message' => 'Message must contain content or an attachment.'], 422);
        }

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentType = null;
        $attachmentSize = null;

        if ($request->hasFile('attachment')) {
            $file           = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $attachmentType = $file->getMimeType();
            $attachmentSize = $file->getSize();
            $attachmentPath = $file->store("messages/{$id}", 'public');
        }

        $user    = Auth::user();
        $message = Message::create([
            'id'              => Str::uuid(),
            'conversation_id' => $id,
            'sender_id'       => $user->id,
            'sender_name'     => $user->name,
            'content'         => $request->input('content'),
            'timestamp'       => now(),
            'read'            => false,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_type' => $attachmentType,
            'attachment_size' => $attachmentSize,
            'reactions'       => [],
        ]);

        // Update conversation preview
        $conversation->update([
            'last_message'      => $request->filled('content')
                ? $request->input('content')
                : "📎 {$attachmentName}",
            'last_message_time' => now(),
            'unread_count'      => $conversation->unread_count + 1,
        ]);

        // Broadcast real-time event via Reverb
        broadcast(new MessageSent($message))->toOthers();

        return response()->json(['message' => 'Sent.', 'data' => $message], 201);
    }

    /**
     * POST /api/v1/messages/{id}/react
     * Toggle an emoji reaction on a message.
     */
    public function react(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'emoji' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $message = Message::findOrFail($id);
        $userId  = Auth::id();
        $emoji   = $request->input('emoji');

        $reactions = $message->reactions ?? [];

        if (isset($reactions[$emoji]) && in_array($userId, $reactions[$emoji])) {
            // Toggle off
            $reactions[$emoji] = array_values(array_filter(
                $reactions[$emoji],
                fn($uid) => $uid !== $userId
            ));
            if (empty($reactions[$emoji])) {
                unset($reactions[$emoji]);
            }
        } else {
            // Toggle on
            $reactions[$emoji]   = $reactions[$emoji] ?? [];
            $reactions[$emoji][] = $userId;
        }

        $message->update(['reactions' => $reactions]);

        broadcast(new ReactionAdded(
            messageId:       $message->id,
            conversationId:  $message->conversation_id,
            userId:          $userId,
            emoji:           $emoji,
            reactions:       $reactions,
        ))->toOthers();

        return response()->json(['data' => ['reactions' => $reactions]]);
    }

    /**
     * PATCH /api/v1/conversations/{id}/messages/read
     * Mark all messages in a conversation as read.
     */
    public function markRead(string $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);
        $this->authorizeConversation($conversation);

        Message::where('conversation_id', $id)
            ->where('sender_id', '!=', Auth::id())
            ->where('read', false)
            ->update(['read' => true]);

        $conversation->update(['unread_count' => 0]);

        return response()->json(['message' => 'Messages marked as read.']);
    }

    // -----------------------------------------------------------------------

    /**
     * POST /api/v1/conversations/{id}/typing
     * Broadcast typing indicator to conversation participants
     */
    public function typing(Request $request, string $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);
        $this->authorizeConversation($conversation);

        $validator = Validator::make($request->all(), [
            'is_typing' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Auth::user();

        broadcast(new UserTyping(
            conversationId: $id,
            userId: $user->id,
            userName: $user->name,
            isTyping: $request->boolean('is_typing'),
        ))->toOthers();

        return response()->json(['message' => 'Typing status broadcasted.']);
    }

    /**
     * DELETE /api/v1/messages/{id}
     * Soft delete a message (for me or for everyone)
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $message = Message::findOrFail($id);
        $conversation = $message->conversation;
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'deletion_type' => 'required|string|in:me,everyone',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $deletionType = $request->input('deletion_type');

        if ($deletionType === 'everyone') {
            // Check permission to delete for everyone
            if (!ChatPolicy::canDeleteForEveryone($user, $message, $conversation)) {
                return response()->json(['message' => 'Forbidden. You cannot delete this message for everyone.'], 403);
            }

            // Store original content for audit
            $message->update([
                'original_content' => $message->content,
                'content' => null,
                'deleted_at' => now(),
                'deleted_by' => $user->id,
                'deletion_type' => Message::DELETE_FOR_EVERYONE,
            ]);

            // Broadcast deletion event
            broadcast(new MessageDeleted(
                messageId: $id,
                conversationId: $conversation->id,
                deletedBy: $user->id,
                deletionType: 'everyone',
            ))->toOthers();

            return response()->json([
                'message' => 'Message deleted for everyone.',
                'deletion_type' => 'everyone',
            ]);
        }

        // Delete for me - add to participant's hidden messages (implementation depends on frontend needs)
        // For now, return success - frontend can track hidden message IDs locally
        return response()->json([
            'message' => 'Message hidden for you.',
            'deletion_type' => 'me',
        ]);
    }

    /**
     * PATCH /api/v1/messages/{id}/restore
     * Restore a soft-deleted message (admin only)
     */
    public function restore(string $id): JsonResponse
    {
        $message = Message::withTrashed()->findOrFail($id);
        $conversation = $message->conversation;
        $user = Auth::user();

        if (!ChatPolicy::canRestoreMessage($user, $message, $conversation)) {
            return response()->json(['message' => 'Forbidden. Only admin can restore messages.'], 403);
        }

        $message->update([
            'content' => $message->original_content ?? $message->content,
            'deleted_at' => null,
            'deleted_by' => null,
            'deletion_type' => null,
            'original_content' => null,
        ]);

        return response()->json([
            'message' => 'Message restored.',
            'data' => $message->fresh(),
        ]);
    }

    /**
     * POST /api/v1/messages/{id}/pin
     * Pin or unpin a message
     */
    public function pin(Request $request, string $id): JsonResponse
    {
        $message = Message::findOrFail($id);
        $conversation = $message->conversation;
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'is_pinned' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!ChatPolicy::canPinMessage($user, $conversation)) {
            return response()->json(['message' => 'Forbidden. You cannot pin messages in this conversation.'], 403);
        }

        $isPinned = $request->boolean('is_pinned');

        $message->update([
            'is_pinned' => $isPinned,
            'pinned_by' => $isPinned ? $user->id : null,
            'pinned_at' => $isPinned ? now() : null,
        ]);

        broadcast(new MessagePinned(
            messageId: $id,
            conversationId: $conversation->id,
            pinnedBy: $user->id,
            isPinned: $isPinned,
        ))->toOthers();

        return response()->json([
            'message' => $isPinned ? 'Message pinned.' : 'Message unpinned.',
            'data' => $message->fresh(),
        ]);
    }

    /**
     * GET /api/v1/conversations/{id}/pinned-messages
     * Get pinned messages in a conversation
     */
    public function pinnedMessages(string $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);
        $this->authorizeConversation($conversation);

        $messages = Message::where('conversation_id', $id)
            ->where('is_pinned', true)
            ->whereNull('deleted_at')
            ->orderBy('pinned_at', 'desc')
            ->get();

        return response()->json(['data' => $messages]);
    }

    /**
     * POST /api/v1/messages/{id}/delivered
     * Mark message as delivered for the current user
     */
    public function markDelivered(string $id): JsonResponse
    {
        $message = Message::findOrFail($id);
        $conversation = $message->conversation;
        $user = Auth::user();

        $this->authorizeConversation($conversation);

        // Don't mark own messages
        if ($message->sender_id === $user->id) {
            return response()->json(['message' => 'Cannot mark own message as delivered.']);
        }

        $message->markDelivered($user->id);

        // Get the status record to broadcast
        $status = $message->getStatusForUser($user->id);

        if ($status) {
            broadcast(new MessageStatusUpdated(
                messageId: $id,
                conversationId: $conversation->id,
                userId: $user->id,
                status: 'delivered',
                deliveredAt: $status->delivered_at?->toIso8601String(),
            ))->toOthers();
        }

        return response()->json(['message' => 'Message marked as delivered.']);
    }

    /**
     * POST /api/v1/messages/{id}/read
     * Mark a specific message as read
     */
    public function markMessageRead(string $id): JsonResponse
    {
        $message = Message::findOrFail($id);
        $conversation = $message->conversation;
        $user = Auth::user();

        $this->authorizeConversation($conversation);

        // Don't mark own messages
        if ($message->sender_id === $user->id) {
            return response()->json(['message' => 'Cannot mark own message as read.']);
        }

        $message->markRead($user->id);

        // Get the status record to broadcast
        $status = $message->getStatusForUser($user->id);

        if ($status) {
            broadcast(new MessageStatusUpdated(
                messageId: $id,
                conversationId: $conversation->id,
                userId: $user->id,
                status: 'read',
                deliveredAt: $status->delivered_at?->toIso8601String(),
                readAt: $status->read_at?->toIso8601String(),
            ))->toOthers();
        }

        return response()->json(['message' => 'Message marked as read.']);
    }

    // -----------------------------------------------------------------------

    private function authorizeConversation(Conversation $conversation): void
    {
        $userId = Auth::id();

        // Check participant table first (for group chats)
        if ($conversation->participants()->where('user_id', $userId)->exists()) {
            return;
        }

        // Fallback to owner/participant for direct chats
        if ($conversation->owner_user_id === $userId || $conversation->participant_user_id === $userId) {
            return;
        }

        abort(403, 'Forbidden.');
    }
}
