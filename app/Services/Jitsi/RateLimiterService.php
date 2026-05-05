<?php

namespace App\Services\Jitsi;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter as LaravelRateLimiter;

class RateLimiterService
{
    /**
     * Check if user can make transcription request
     * Rate: 1 request per 10 seconds per user
     */
    public function canTranscribe(string $userId): bool
    {
        $key = "transcribe:{$userId}";
        
        return LaravelRateLimiter::attempt(
            $key,
            1, // max attempts
            function () {},
            10 // decay seconds
        );
    }

    /**
     * Get remaining transcription attempts
     */
    public function getTranscribeRemaining(string $userId): int
    {
        $key = "transcribe:{$userId}";
        return LaravelRateLimiter::remaining($key, 1);
    }

    /**
     * Get time until next transcription attempt available
     */
    public function getTranscribeRetryAfter(string $userId): int
    {
        $key = "transcribe:{$userId}";
        return LaravelRateLimiter::availableIn($key);
    }

    /**
     * Check AI question rate limit
     * Rate: 5 requests per minute per user
     */
    public function canAskAI(string $userId): bool
    {
        $key = "ask-ai:{$userId}";
        
        return LaravelRateLimiter::attempt(
            $key,
            5, // max attempts
            function () {},
            60 // decay seconds (1 minute)
        );
    }

    /**
     * Check poll creation rate limit
     * Rate: 10 polls per hour per instructor per session
     */
    public function canCreatePoll(string $userId, string $sessionId): bool
    {
        $key = "create-poll:{$userId}:{$sessionId}";
        
        return LaravelRateLimiter::attempt(
            $key,
            10, // max attempts
            function () {},
            3600 // decay seconds (1 hour)
        );
    }

    /**
     * Clear rate limit for a user (useful for testing)
     */
    public function clearLimit(string $type, string $userId, ?string $sessionId = null): void
    {
        $key = $sessionId ? "{$type}:{$userId}:{$sessionId}" : "{$type}:{$userId}";
        Cache::forget($key);
    }
}
