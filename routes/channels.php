<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Conversation;

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

    return $conversation->owner_user_id === $user->id
        || $conversation->participant_user_id === $user->id;
});

Broadcast::channel('online-users', function ($user) {
    // Return user info that gets merged into the presence channel member list
    return [
        'id'       => $user->id,
        'name'     => $user->name,
        'initials' => $user->initials ?? strtoupper(substr($user->name, 0, 2)),
    ];
});
