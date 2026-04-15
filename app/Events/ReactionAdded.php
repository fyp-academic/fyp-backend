<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReactionAdded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $messageId,
        public readonly string $conversationId,
        public readonly string $userId,
        public readonly string $emoji,
        public readonly array  $reactions,     // full updated reactions map
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->conversationId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reaction.added';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'      => $this->messageId,
            'conversation_id' => $this->conversationId,
            'user_id'         => $this->userId,
            'emoji'           => $this->emoji,
            'reactions'       => $this->reactions,
        ];
    }
}
