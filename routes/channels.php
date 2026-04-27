<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;
use App\Models\UserPresence;
use App\Events\UserStatusUpdated;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Private channel: only participants of the conversation may subscribe.
| Presence channel: all authenticated users join the global online roster.
*/

Broadcast::channel('conversation.{conversationId}', function ($user, string $conversationId) {
    $conversation = Conversation::find($conversationId);
    if (! $conversation) return false;

    // Admin can access any conversation
    if ($user->role === 'admin') {
        // Update user's presence when joining
        UserPresence::updateOrCreate(
            ['user_id' => $user->id],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'status' => 'online',
                'last_seen_at' => now(),
                'last_active_conversation_id' => $conversationId,
            ]
        );
        broadcast(new UserStatusUpdated($user->id, 'online'))->toOthers();
        return true;
    }

    // Check if user is a participant via participants table (for all chat types)
    if ($conversation->participants()->where('user_id', $user->id)->exists()) {
        // Check if user is blocked
        $participant = $conversation->participants()->where('user_id', $user->id)->first();
        if ($participant && $participant->is_blocked) {
            return false;
        }

        // Update user's presence
        UserPresence::updateOrCreate(
            ['user_id' => $user->id],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'status' => 'online',
                'last_seen_at' => now(),
                'last_active_conversation_id' => $conversationId,
            ]
        );
        broadcast(new UserStatusUpdated($user->id, 'online'))->toOthers();
        return true;
    }

    // Fallback for legacy direct chats (owner/participant)
    if ($conversation->owner_user_id === $user->id || $conversation->participant_user_id === $user->id) {
        // Update user's presence
        UserPresence::updateOrCreate(
            ['user_id' => $user->id],
            [
                'id' => \Illuminate\Support\Str::uuid(),
                'status' => 'online',
                'last_seen_at' => now(),
                'last_active_conversation_id' => $conversationId,
            ]
        );
        broadcast(new UserStatusUpdated($user->id, 'online'))->toOthers();
        return true;
    }

    return false;
});

Broadcast::channel('online-users', function ($user) {
    // Update or create presence record
    UserPresence::updateOrCreate(
        ['user_id' => $user->id],
        [
            'id' => \Illuminate\Support\Str::uuid(),
            'status' => 'online',
            'last_seen_at' => now(),
        ]
    );

    // Broadcast status update
    broadcast(new UserStatusUpdated($user->id, 'online'))->toOthers();

    // Return user info that gets merged into the presence channel member list
    return [
        'id'       => $user->id,
        'name'     => $user->name,
        'initials' => $user->initials ?? strtoupper(substr($user->name, 0, 2)),
    ];
});
