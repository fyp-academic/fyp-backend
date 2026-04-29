<?php

namespace App\Adapters;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class PushAdapter implements ChannelAdapterInterface
{
    /**
     * Send notification via push (FCM or Twilio for SMS-based push).
     * Note: Full implementation requires FCM SDK setup.
     */
    public function send(Notification $notification): bool
    {
        try {
            // Get user's device tokens
            $devices = \App\Models\UserDevice::where('user_id', $notification->user_id)
                ->whereNotNull('push_token')
                ->pluck('push_token');

            if ($devices->isEmpty()) {
                Log::info('No push tokens found for user', [
                    'user_id' => $notification->user_id,
                ]);
                // Mark as delivered anyway - user has no devices
                $notification->markAsDelivered();
                return true;
            }

            // TODO: Integrate with Firebase Cloud Messaging (FCM)
            // For now, log that we would send push
            Log::info('Push notification would be sent', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'tokens_count' => $devices->count(),
                'title' => $notification->title,
                'body' => $notification->body,
            ]);

            // Example FCM implementation would go here:
            // $fcm = new FCMClient(config('services.fcm.key'));
            // foreach ($devices as $token) {
            //     $fcm->send([
            //         'to' => $token,
            //         'notification' => [
            //             'title' => $notification->title,
            //             'body' => $notification->body,
            //         ],
            //         'data' => $notification->payload ?? [],
            //     ]);
            // }

            $notification->markAsDelivered();
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send push notification', [
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
        return 'push';
    }
}
