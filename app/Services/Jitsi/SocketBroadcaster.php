<?php

namespace App\Services\Jitsi;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class SocketBroadcaster
{
    private string $channel;

    public function __construct()
    {
        $this->channel = config('broadcasting.redis.channel', 'jitsi-events');;
    }

    /**
     * Broadcast transcript update to Socket.io server
     */
    public function broadcastTranscript(string $sessionId, array $transcriptData): void
    {
        $this->publish([
            'type' => 'transcript:new',
            'data' => [
                'session_id' => $sessionId,
                'transcript_id' => $transcriptData['id'] ?? null,
                'speaker' => $transcriptData['speaker'],
                'text' => $transcriptData['text'],
                'segments' => $transcriptData['segments'] ?? [],
                'time' => $transcriptData['timestamp'] ?? now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Broadcast session started event
     */
    public function broadcastSessionStarted(string $sessionId): void
    {
        $this->publish([
            'type' => 'session:started',
            'data' => [
                'session_id' => $sessionId,
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Broadcast session ended event
     */
    public function broadcastSessionEnded(string $sessionId): void
    {
        $this->publish([
            'type' => 'session:ended',
            'data' => [
                'session_id' => $sessionId,
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Broadcast participant joined
     */
    public function broadcastParticipantJoined(string $sessionId, array $userData): void
    {
        $this->publish([
            'type' => 'participant:joined',
            'data' => [
                'session_id' => $sessionId,
                'user_id' => $userData['id'],
                'name' => $userData['name'],
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Broadcast participant left
     */
    public function broadcastParticipantLeft(string $sessionId, string $userId): void
    {
        $this->publish([
            'type' => 'participant:left',
            'data' => [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Broadcast mute status change
     */
    public function broadcastMuteStatus(string $sessionId, string $userId, bool $muted, bool $videoMuted): void
    {
        $this->publish([
            'type' => 'participant:mute',
            'data' => [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'muted' => $muted,
                'video_muted' => $videoMuted,
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Broadcast hand raise/lower
     */
    public function broadcastHandStatus(string $sessionId, string $userId, bool $raised): void
    {
        $this->publish([
            'type' => 'participant:hand',
            'data' => [
                'session_id' => $sessionId,
                'user_id' => $userId,
                'raised' => $raised,
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Broadcast chat message
     */
    public function broadcastChatMessage(string $sessionId, array $messageData): void
    {
        $this->publish([
            'type' => 'chat:message',
            'data' => [
                'session_id' => $sessionId,
                'message_id' => $messageData['id'] ?? null,
                'sender' => $messageData['sender'] ?? null,
                'text' => $messageData['text'],
                'is_ai' => $messageData['is_ai'] ?? false,
                'reply_to' => $messageData['reply_to'] ?? null,
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Broadcast AI answer
     */
    public function broadcastAIAnswer(string $sessionId, string $question, string $answer): void
    {
        $this->publish([
            'type' => 'chat:message',
            'data' => [
                'session_id' => $sessionId,
                'sender' => [
                    'user_id' => 'ai-assistant',
                    'name' => 'AI Assistant',
                    'is_ai' => true,
                ],
                'text' => $answer,
                'reply_to' => $question,
                'is_ai' => true,
                'timestamp' => now()->toIso8601String(),
            ]
        ]);
    }

    /**
     * Publish event to Redis
     */
    private function publish(array $event): void
    {
        try {
            Redis::publish($this->channel, json_encode($event));
        } catch (\Exception $e) {
            Log::error('Socket broadcast failed: ' . $e->getMessage(), $event);
        }
    }
}
