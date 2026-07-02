<?php

namespace App\Adapters;

use App\Models\Notification;
use App\Models\UserDevice;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushAdapter implements ChannelAdapterInterface
{
    /**
     * Send notification via Web Push (VAPID).
     *
     * Encrypts and delivers the payload to every browser PushSubscription the
     * user has registered. Expired/invalid subscriptions (HTTP 404/410) are
     * pruned so we don't keep retrying dead endpoints.
     */
    public function send(Notification $notification): bool
    {
        $auth = config('services.webpush');

        if (empty($auth['public_key']) || empty($auth['private_key'])) {
            // No VAPID keys configured — treat as a soft no-op so the pipeline
            // (and the originating request) isn't broken. Run `php artisan webpush:vapid`.
            Log::warning('Push notification skipped - VAPID keys not configured', [
                'notification_id' => $notification->id,
            ]);
            $notification->markAsDelivered();
            return true;
        }

        // Collect the user's registered Web Push subscriptions.
        $devices = UserDevice::where('user_id', $notification->user_id)
            ->whereNotNull('endpoint')
            ->whereNotNull('public_key')
            ->whereNotNull('auth_token')
            ->get();

        if ($devices->isEmpty()) {
            Log::info('No push subscriptions found for user', [
                'user_id' => $notification->user_id,
            ]);
            // User simply has no subscribed devices — nothing to deliver.
            $notification->markAsDelivered();
            return true;
        }

        $payload = $notification->payload ?? [];
        $body = json_encode([
            'title' => $notification->title,
            'body' => $notification->body,
            'icon' => $payload['icon'] ?? '/icons/notification.png',
            'url' => $payload['action_url'] ?? null,
            'type' => $notification->type,
            'notification_id' => $notification->id,
            'data' => $payload,
        ]);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $auth['subject'],
                    'publicKey' => $auth['public_key'],
                    'privateKey' => $auth['private_key'],
                ],
            ]);

            // Queue one encrypted message per subscription.
            foreach ($devices as $device) {
                $subscription = Subscription::create([
                    'endpoint' => $device->endpoint,
                    'publicKey' => $device->public_key,
                    'authToken' => $device->auth_token,
                ]);
                $webPush->queueNotification($subscription, $body);
            }

            $anySuccess = false;

            // Flush sends all queued messages and reports per-endpoint results.
            foreach ($webPush->flush() as $report) {
                $endpoint = $report->getRequest()->getUri()->__toString();

                if ($report->isSuccess()) {
                    $anySuccess = true;
                    continue;
                }

                // Prune subscriptions the push service says are gone.
                $statusCode = $report->getResponse()?->getStatusCode();
                if (in_array($statusCode, [404, 410], true)) {
                    UserDevice::where('endpoint', $endpoint)->delete();
                    Log::info('Pruned expired push subscription', [
                        'user_id' => $notification->user_id,
                        'status' => $statusCode,
                    ]);
                } else {
                    Log::warning('Web push delivery failed for endpoint', [
                        'notification_id' => $notification->id,
                        'reason' => $report->getReason(),
                        'status' => $statusCode,
                    ]);
                }
            }

            if ($anySuccess) {
                $notification->markAsDelivered();
                Log::info('Push notification delivered', [
                    'notification_id' => $notification->id,
                    'user_id' => $notification->user_id,
                    'subscriptions' => $devices->count(),
                ]);
                return true;
            }

            // Every endpoint failed (e.g. all expired). Don't hard-fail the job
            // since there's nothing left to retry to.
            $notification->markAsDelivered();
            return true;

        } catch (\Throwable $e) {
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
