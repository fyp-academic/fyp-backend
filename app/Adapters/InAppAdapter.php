<?php

namespace App\Adapters;

use App\Events\NotificationCreated;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class InAppAdapter implements ChannelAdapterInterface
{
    /**
     * Send notification via in-app channel.
     * This inserts to DB and emits WebSocket event.
     */
    public function send(Notification $notification): bool
    {
        try {
            // The notification is already in the database (created during dispatch)
            // Just update the sent status and emit WebSocket event
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Emit WebSocket event for real-time delivery
            // Using Laravel Reverb broadcasting - broadcast to ALL (including sender) so they see their own notifications
            broadcast(new NotificationCreated($notification));

            Log::info('In-app notification sent', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send in-app notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the channel name.
     */
    public function getChannel(): string
    {
        return 'in_app';
    }
}
