<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly \App\Models\Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'               => $this->message->id,
            'conversation_id'  => $this->message->conversation_id,
            'sender_id'        => $this->message->sender_id,
            'sender_name'      => $this->message->sender_name,
            'content'          => $this->message->content,
            'timestamp'        => $this->message->timestamp,
            'reactions'        => $this->message->reactions ?? [],
            'attachment_path'  => $this->message->attachment_path,
            'attachment_name'  => $this->message->attachment_name,
            'attachment_type'  => $this->message->attachment_type,
        ];
    }
}
