<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

class ConversationController extends Controller
{
    /**
     * GET /api/v1/conversations
     * Return all conversations for the authenticated user, sorted by latest message.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::where('owner_user_id', $user->id)
            ->orWhere('participant_user_id', $user->id)
            ->with(['owner', 'participant'])
            ->orderBy('last_message_time', 'desc')
            ->get();

        return response()->json(['data' => $conversations]);
    }

    /**
     * POST /api/v1/conversations
     * Start a new one-to-one conversation thread with another user.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipient_id' => 'required|string|exists:users,id',
            'message'      => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user      = $request->user();
        $recipient = User::findOrFail($request->recipient_id);

        $existing = Conversation::where(function ($q) use ($user, $recipient) {
            $q->where('owner_user_id', $user->id)
              ->where('participant_user_id', $recipient->id);
        })->orWhere(function ($q) use ($user, $recipient) {
            $q->where('owner_user_id', $recipient->id)
              ->where('participant_user_id', $user->id);
        })->first();

        if ($existing) {
            return response()->json(['message' => 'Conversation already exists.', 'data' => $existing], 409);
        }

        $conversation = Conversation::create([
            'id'                   => Str::uuid()->toString(),
            'owner_user_id'        => $user->id,
            'participant_user_id'  => $recipient->id,
            'participant_name'     => $recipient->name,
            'participant_role'     => $recipient->role ?? 'student',
            'last_message'         => $request->message,
            'last_message_time'    => now(),
            'unread_count'         => 1,
        ]);

        Message::create([
            'id'              => Str::uuid()->toString(),
            'conversation_id' => $conversation->id,
            'sender_id'       => $user->id,
            'sender_name'     => $user->name,
            'content'         => $request->message,
            'timestamp'       => now(),
            'read'            => false,
        ]);

        return response()->json(['message' => 'Conversation created.', 'data' => $conversation], 201);
    }

    /**
     * GET /api/v1/conversations/{id}/messages
     * Retrieve all messages in a conversation thread in chronological order.
     */
    public function messages(Request $request, string $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);

        $msgs = Message::where('conversation_id', $id)
            ->orderBy('timestamp')
            ->get();

        return response()->json(['data' => $msgs, 'conversation_id' => $id]);
    }

    /**
     * POST /api/v1/conversations/{id}/messages
     * Post a new message to an existing conversation.
     */
    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conversation = Conversation::findOrFail($id);
        $user         = $request->user();

        $msg = Message::create([
            'id'              => Str::uuid()->toString(),
            'conversation_id' => $id,
            'sender_id'       => $user->id,
            'sender_name'     => $user->name,
            'content'         => $request->message,
            'timestamp'       => now(),
            'read'            => false,
        ]);

        $conversation->update([
            'last_message'      => $request->message,
            'last_message_time' => now(),
        ]);

        $conversation->increment('unread_count');

        return response()->json(['message' => 'Message sent.', 'data' => $msg], 201);
    }

    /**
     * PATCH /api/v1/conversations/{id}/read
     * Mark all unread messages in a conversation as read.
     */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);
        $user         = $request->user();

        Message::where('conversation_id', $id)
            ->where('sender_id', '!=', $user->id)
            ->where('read', false)
            ->update(['read' => true]);

        $conversation->update(['unread_count' => 0]);

        return response()->json(['message' => 'Messages marked as read.', 'conversation_id' => $id]);
    }
}
