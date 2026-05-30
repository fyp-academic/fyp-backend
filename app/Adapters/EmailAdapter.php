<?php

namespace App\Adapters;

use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailAdapter implements ChannelAdapterInterface
{
    /**
     * Send notification via email using SendGrid.
     */
    public function send(Notification $notification): bool
    {
        try {
            $user = $notification->user;

            if (!$user || !$user->email) {
                throw new \Exception('User has no email address');
            }

            $payload = $notification->payload ?? [];

            // Build email data
            $emailData = [
                'subject' => $notification->title,
                'title' => $notification->title,
                'body' => $notification->body,
                'action_url' => $payload['action_url'] ?? null,
                'action_text' => $payload['action_text'] ?? 'View Details',
                'unsubscribe_url' => $this->generateUnsubscribeUrl($user->id),
                'notification_type' => $notification->type,
            ];

            // Send using Laravel Mail with SendGrid transport
            Mail::send('emails.notification', $emailData, function ($message) use ($user, $notification) {
                $message->to($user->email)
                    ->subject($notification->title)
                    ->from(config('mail.from.address'), config('mail.from.name'));
            });

            // Update sent timestamp
            $notification->update(['sent_at' => now()]);

            Log::info('Email notification sent', [
                'notification_id' => $notification->id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send email notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate unsubscribe URL for the user.
     */
    private function generateUnsubscribeUrl(string $userId): string
    {
        $token = hash_hmac('sha256', $userId, config('app.key'));
        return url("/unsubscribe?user={$userId}&token={$token}");
    }

    /**
     * Get the channel name.
     */
    public function getChannel(): string
    {
        return 'email';
    }
}
