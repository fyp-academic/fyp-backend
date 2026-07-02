<?php

namespace App\Services;

use App\Constants\NotificationTypes;
use App\Events\NotificationBadgeUpdated;
use App\Events\NotificationCreated;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Dispatch a notification to the queue.
     * Never sends synchronously - always queues.
     *
     * @param string $type Notification type constant
     * @param string $userId Target user ID
     * @param array $payload Notification data (title, body, action_url, etc.)
     * @param string|null $contextId Unique context ID for deduplication (e.g., assignment_id)
     * @return Notification|null The created notification or null if duplicate
     */
    public function dispatch(
        string $type,
        string $userId,
        array $payload,
        ?string $contextId = null
    ): ?Notification {
        try {
            // Respect the user's global mute setting
            if ($this->isGloballyMuted($userId)) {
                Log::info('Notification skipped - user globally muted', [
                    'type' => $type,
                    'user_id' => $userId,
                ]);
                return null;
            }

            // Generate dedup key
            $dedupKey = $this->generateDedupKey($type, $userId, $contextId);

            // Check for duplicates
            if ($this->isDuplicate($dedupKey)) {
                Log::info('Duplicate notification skipped', [
                    'type' => $type,
                    'user_id' => $userId,
                    'dedup_key' => $dedupKey,
                ]);
                return null;
            }

            // Get user's enabled channels for this notification type
            $preferences = $this->getUserPreferences($userId, $type);
            $enabledChannels = array_filter($preferences, fn($p) => $p->enabled);

            if (empty($enabledChannels)) {
                Log::info('Notification skipped - no enabled channels', [
                    'type' => $type,
                    'user_id' => $userId,
                ]);
                return null;
            }

            // Create notification record for each channel
            $notifications = [];
            foreach ($enabledChannels as $pref) {
                $channelDedupKey = $this->channelDedupKey($dedupKey, $pref->channel);

                $notification = Notification::create([
                    'user_id' => $userId,
                    'type' => $type,
                    'channel' => $pref->channel,
                    'title' => $payload['title'] ?? 'New Notification',
                    'body' => $payload['body'] ?? null,
                    'payload' => $payload,
                    'dedup_key' => $channelDedupKey,
                    'status' => 'pending',
                    'retry_count' => 0,
                ]);
                $notifications[] = $notification;

                // Hybrid delivery:
                //  - Batched (daily/weekly) channels are collected into a digest.
                //  - Push/SMS inside quiet hours are deferred to the real queue so
                //    they fire once quiet hours end (handled by SendNotificationJob).
                //  - Every other "instant" channel (email, push, in_app) is delivered
                //    synchronously via dispatchSync, so it works even when no queue
                //    worker is running. The job logic (preference re-check, quiet
                //    hours, adapter send, retries/dead-letter) is reused as-is.
                if ($pref->shouldBatch()) {
                    $this->addToDigest($notification, $pref);
                } elseif (in_array($pref->channel, ['push', 'sms'], true) && $pref->isInQuietHours()) {
                    SendNotificationJob::dispatch($notification)->onQueue('notifications');
                } else {
                    // Synchronous delivery must never break the originating request.
                    try {
                        SendNotificationJob::dispatchSync($notification);
                    } catch (\Throwable $e) {
                        Log::error('Synchronous notification delivery failed', [
                            'notification_id' => $notification->id,
                            'channel' => $pref->channel,
                            'error' => $e->getMessage(),
                        ]);
                        $notification->markAsFailed($e->getMessage());
                    }
                }
            }

            Log::info('Notification dispatched', [
                'type' => $type,
                'user_id' => $userId,
                'channels' => array_map(fn($p) => $p->channel, $enabledChannels),
                'dedup_key' => $dedupKey,
            ]);

            return $notifications[0] ?? null;

        } catch (\Exception $e) {
            Log::error('Failed to dispatch notification', [
                'type' => $type,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate a deterministic deduplication key
     */
    private function generateDedupKey(string $type, string $userId, ?string $contextId): string
    {
        if ($contextId) {
            return "{$type}__{$userId}__{$contextId}";
        }
        // Fallback to timestamp-based unique key if no context
        return "{$type}__{$userId}__" . Str::random(16);
    }

    /**
     * Check if a notification with this dedup key already exists
     */
    private function isDuplicate(string $dedupKey): bool
    {
        return Notification::where('dedup_key', $dedupKey)
            ->orWhere('dedup_key', 'like', $dedupKey . '__channel_%')
            ->exists();
    }

    /**
     * Get user preferences for a notification type across all channels
     *
     * @return NotificationPreference[]
     */
    private function getUserPreferences(string $userId, string $type): array
    {
        $prefs = NotificationPreference::where('user_id', $userId)
            ->where('notification_type', $type)
            ->get();

        // If no preferences exist, create defaults
        if ($prefs->isEmpty()) {
            return $this->createDefaultPreferences($userId, $type);
        }

        return $prefs->all();
    }

    /**
     * Create default preferences for a user and notification type
     *
     * @return NotificationPreference[]
     */
    private function createDefaultPreferences(string $userId, string $type): array
    {
        $defaultChannels = NotificationTypes::getDefaultChannels($type);
        $prefs = [];

        foreach ($defaultChannels as $channel) {
            $prefs[] = NotificationPreference::create([
                'user_id' => $userId,
                'notification_type' => $type,
                'channel' => $channel,
                'enabled' => true,
                'digest_mode' => 'instant',
            ]);
        }

        return $prefs;
    }

    private function channelDedupKey(string $dedupKey, string $channel): string
    {
        return "{$dedupKey}__channel_{$channel}";
    }

    /**
     * Add notification to digest for batching
     */
    private function addToDigest(Notification $notification, NotificationPreference $pref): void
    {
        $digest = \App\Models\NotificationDigest::firstOrCreate(
            [
                'user_id' => $notification->user_id,
                'channel' => $notification->channel,
                'frequency' => $pref->digest_mode,
            ],
            [
                'next_send_at' => $this->calculateNextDigestTime($pref->digest_mode),
                'pending_ids' => [],
            ]
        );

        $digest->addPendingNotification($notification->id);

        Log::info('Notification added to digest', [
            'notification_id' => $notification->id,
            'user_id' => $notification->user_id,
            'frequency' => $pref->digest_mode,
        ]);
    }

    /**
     * Calculate next digest send time
     */
    private function calculateNextDigestTime(string $frequency): \DateTime
    {
        return match($frequency) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            default => now()->addDay(),
        };
    }

    /**
     * Seed default preferences for a new user
     */
    public function seedDefaultPreferences(string $userId): void
    {
        $types = NotificationTypes::all();

        foreach ($types as $type) {
            $channels = NotificationTypes::getDefaultChannels($type);
            foreach ($channels as $channel) {
                NotificationPreference::firstOrCreate(
                    [
                        'user_id' => $userId,
                        'notification_type' => $type,
                        'channel' => $channel,
                    ],
                    [
                        'enabled' => true,
                        'digest_mode' => 'instant',
                    ]
                );
            }
        }

        Log::info('Default preferences seeded for user', ['user_id' => $userId]);
    }

    /**
     * Toggle global mute for a user
     */
    public function setGlobalMute(string $userId, bool $muted): void
    {
        // Store mute status in cache or user settings
        cache()->put("user:{$userId}:notifications:muted", $muted, now()->addDays(30));

        Log::info('Global mute toggled', [
            'user_id' => $userId,
            'muted' => $muted,
        ]);
    }

    /**
     * Check if user has global mute enabled
     */
    public function isGloballyMuted(string $userId): bool
    {
        return cache()->get("user:{$userId}:notifications:muted", false);
    }

    /**
     * Send an in-app notification immediately (marked as sent) respecting user preferences.
     * Used for course updates where students should see the notification right away.
     */
    public function sendToUser(
        string $userId,
        string $type,
        string $channel,
        string $title,
        string $body,
        array $payload = [],
        ?string $contextId = null
    ): ?Notification {
        // Respect the user's global mute setting
        if ($this->isGloballyMuted($userId)) {
            return null;
        }

        // Check if user has this type enabled for the channel; create default if missing
        $pref = NotificationPreference::where([
            'user_id' => $userId,
            'notification_type' => $type,
            'channel' => $channel,
            'enabled' => true,
        ])->first();

        if (!$pref) {
            // Create default preference if none exists (like dispatch does)
            $defaultChannels = NotificationTypes::getDefaultChannels($type);
            if (!in_array($channel, $defaultChannels)) {
                return null; // Channel not in defaults for this type
            }
            $pref = NotificationPreference::create([
                'user_id' => $userId,
                'notification_type' => $type,
                'channel' => $channel,
                'enabled' => true,
                'digest_mode' => 'instant',
            ]);
        }

        // Check for duplicates
        $dedupKey = $this->generateDedupKey($type, $userId, $contextId);
        if ($this->isDuplicate($dedupKey)) {
            return null;
        }

        // Create in-app notification marked as sent (visible immediately)
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'channel' => $channel,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
            'dedup_key' => $dedupKey,
            'status' => 'sent', // Mark as sent so it appears immediately
            'sent_at' => now(),
            'retry_count' => 0,
        ]);

        // Broadcast real-time events
        broadcast(new NotificationCreated($notification));
        $unreadCount = Notification::where('user_id', $userId)
            ->where('channel', 'in_app')
            ->whereIn('status', ['sent', 'delivered'])
            ->count();
        broadcast(new NotificationBadgeUpdated($userId, $unreadCount));

        return $notification;
    }
}
