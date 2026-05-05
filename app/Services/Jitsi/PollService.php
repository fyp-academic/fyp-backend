<?php

namespace App\Services\Jitsi;

use App\Models\Session;
use App\Models\SessionPoll;
use App\Models\SessionPollVote;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PollService
{
    private SocketBroadcaster $broadcaster;

    public function __construct()
    {
        $this->broadcaster = new SocketBroadcaster();
    }

    /**
     * Create a new poll for a session
     */
    public function createPoll(string $sessionId, string $userId, array $data): SessionPoll
    {
        $session = Session::findOrFail($sessionId);
        
        // Verify user is instructor
        if ($session->instructor_id !== $userId) {
            throw new \Exception('Only instructors can create polls');
        }

        $poll = SessionPoll::create([
            'session_id' => $sessionId,
            'question' => $data['question'],
            'options' => $data['options'],
            'is_multiple_choice' => $data['is_multiple_choice'] ?? false,
            'created_by' => $userId,
            'is_active' => true,
        ]);

        // Broadcast poll to all participants
        $this->broadcaster->broadcastChatMessage($sessionId, [
            'id' => 'poll_' . $poll->id,
            'type' => 'poll_created',
            'sender' => [
                'id' => 'system',
                'name' => 'Poll System',
                'isAI' => false,
            ],
            'text' => "New poll: {$poll->question}",
            'is_ai' => false,
            'timestamp' => now()->toIso8601String(),
            'poll_data' => [
                'poll_id' => $poll->id,
                'question' => $poll->question,
                'options' => $poll->options,
                'is_multiple_choice' => $poll->is_multiple_choice,
            ],
        ]);

        Log::info("Poll created for session {$sessionId} by user {$userId}");

        return $poll;
    }

    /**
     * Submit a vote for a poll
     */
    public function vote(string $pollId, string $userId, int $optionIndex): SessionPollVote
    {
        $poll = SessionPoll::findOrFail($pollId);

        if (!$poll->is_active) {
            throw new \Exception('Poll is no longer active');
        }

        if ($poll->ended_at && $poll->ended_at->isPast()) {
            throw new \Exception('Poll has ended');
        }

        // Validate option index
        if (!isset($poll->options[$optionIndex])) {
            throw new \Exception('Invalid option');
        }

        // Check if user already voted (for single choice)
        if (!$poll->is_multiple_choice) {
            $existingVote = SessionPollVote::where('poll_id', $pollId)
                ->where('user_id', $userId)
                ->first();
            
            if ($existingVote) {
                $existingVote->update(['option_index' => $optionIndex]);
                $vote = $existingVote;
            } else {
                $vote = SessionPollVote::create([
                    'poll_id' => $pollId,
                    'user_id' => $userId,
                    'option_index' => $optionIndex,
                ]);
            }
        } else {
            // For multiple choice, allow multiple votes from same user
            $vote = SessionPollVote::create([
                'poll_id' => $pollId,
                'user_id' => $userId,
                'option_index' => $optionIndex,
            ]);
        }

        // Broadcast updated results
        $this->broadcastResults($poll);

        return $vote;
    }

    /**
     * Get poll results
     */
    public function getResults(string $pollId): array
    {
        $poll = SessionPoll::findOrFail($pollId);
        return $poll->getResults();
    }

    /**
     * End a poll
     */
    public function endPoll(string $pollId, string $userId): SessionPoll
    {
        $poll = SessionPoll::findOrFail($pollId);
        $session = $poll->session;

        // Verify user is instructor
        if ($session->instructor_id !== $userId) {
            throw new \Exception('Only instructors can end polls');
        }

        $poll->update([
            'is_active' => false,
            'ended_at' => now(),
        ]);

        // Broadcast final results
        $this->broadcastResults($poll, true);

        Log::info("Poll {$pollId} ended by user {$userId}");

        return $poll->fresh();
    }

    /**
     * Get active polls for a session
     */
    public function getActivePolls(string $sessionId): array
    {
        return SessionPoll::where('session_id', $sessionId)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>', now());
            })
            ->get()
            ->map(fn ($poll) => [
                'id' => $poll->id,
                'question' => $poll->question,
                'options' => $poll->options,
                'is_multiple_choice' => $poll->is_multiple_choice,
                'has_voted' => SessionPollVote::where('poll_id', $poll->id)
                    ->where('user_id', Auth::id())
                    ->exists(),
            ])
            ->toArray();
    }

    /**
     * Broadcast poll results to all participants
     */
    private function broadcastResults(SessionPoll $poll, bool $isFinal = false): void
    {
        $results = $poll->getResults();

        $this->broadcaster->broadcastChatMessage($poll->session_id, [
            'id' => 'poll_results_' . $poll->id,
            'type' => $isFinal ? 'poll_ended' : 'poll_updated',
            'sender' => [
                'id' => 'system',
                'name' => 'Poll System',
                'isAI' => false,
            ],
            'text' => $isFinal ? "Poll ended: {$poll->question}" : "Poll updated: {$poll->question}",
            'is_ai' => false,
            'timestamp' => now()->toIso8601String(),
            'poll_results' => [
                'poll_id' => $poll->id,
                'question' => $poll->question,
                'is_final' => $isFinal,
                'results' => $results,
            ],
        ]);
    }
}
