<?php

namespace App\Events;

use App\Models\SessionTranscript;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranscriptUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public SessionTranscript $transcript;

    /**
     * Create a new event instance.
     */
    public function __construct(SessionTranscript $transcript)
    {
        $this->transcript = $transcript;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('session.' . $this->transcript->session_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'transcript.new';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->transcript->id,
            'session_id' => $this->transcript->session_id,
            'user_id' => $this->transcript->user_id,
            'speaker' => $this->transcript->speaker_name,
            'text' => $this->transcript->text,
            'segments' => $this->transcript->segments,
            'time' => $this->transcript->timestamp->toIso8601String(),
        ];
    }
}
