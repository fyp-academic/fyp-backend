<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\ReactionAdded;
use App\Models\Conversation;
use App\Models\Message;
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

    private function authorizeConversation(Conversation $conversation): void
    {
        $userId = Auth::id();
        if ($conversation->owner_user_id !== $userId && $conversation->participant_user_id !== $userId) {
            abort(403, 'Forbidden.');
        }
    }
}
