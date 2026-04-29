<?php

namespace App\Adapters;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class SmsAdapter implements ChannelAdapterInterface
{
    /**
     * Send notification via SMS using Twilio.
     * Note: Full implementation requires Twilio SDK setup.
     */
    public function send(Notification $notification): bool
    {
        try {
            $user = $notification->user;

            if (!$user || !$user->phone) {
                Log::info('User has no phone number for SMS', [
                    'user_id' => $notification->user_id,
                ]);
                // Mark as failed since SMS requires phone
                return false;
            }

            // TODO: Integrate with Twilio
            // For now, log that we would send SMS
            Log::info('SMS notification would be sent', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'phone' => $user->phone,
                'message' => $this->truncateMessage($notification->body ?? $notification->title),
            ]);

            // Example Twilio implementation would go here:
            // $twilio = new TwilioClient(
            //     config('services.twilio.sid'),
            //     config('services.twilio.token')
            // );
            // $twilio->messages->create($user->phone, [
            //     'from' => config('services.twilio.from'),
            //     'body' => $this->truncateMessage($notification->body ?? $notification->title),
            // ]);

            $notification->markAsDelivered();
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send SMS notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Truncate message to SMS limit (160 chars).
     */
    private function truncateMessage(?string $message): string
    {
        if (!$message) {
            return 'New notification';
        }
        return strlen($message) > 160 ? substr($message, 0, 157) . '...' : $message;
    }

    /**
     * Get the channel name.
     */
    public function getChannel(): string
    {
        return 'sms';
    }
}
