<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranscriptCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $sessionId,
        public readonly string $transcriptId,
        public readonly string $speaker,
        public readonly string $text,
        public readonly string $timestamp,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("session.{$this->sessionId}")];
    }

    public function broadcastAs(): string
    {
        return 'transcript.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'        => $this->transcriptId,
            'speaker'   => $this->speaker,
            'text'      => $this->text,
            'timestamp' => $this->timestamp,
        ];
    }
}
