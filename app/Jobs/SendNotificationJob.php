<?php

namespace App\Jobs;

use App\Adapters\ChannelAdapterFactory;
use App\Models\Notification;
use App\Models\NotificationPreference;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     * Exponential backoff: 5s, 25s, 125s
     */
    public function backoff(): array
    {
        return [5, 25, 125];
    }

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public $maxExceptions = 3;

    /**
     * The notification to send.
     */
    public Notification $notification;

    /**
     * Create a new job instance.
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Refresh notification to get latest state
            $this->notification->refresh();

            // Skip if already sent or read
            if (in_array($this->notification->status, ['sent', 'delivered', 'read'])) {
                Log::info('Notification already processed, skipping', [
                    'notification_id' => $this->notification->id,
                    'status' => $this->notification->status,
                ]);
                return;
            }

            // Re-check preferences inside worker (may have changed since dispatch)
            $preference = NotificationPreference::where([
                'user_id' => $this->notification->user_id,
                'notification_type' => $this->notification->type,
                'channel' => $this->notification->channel,
            ])->first();

            if (!$preference || !$preference->enabled) {
                Log::info('Notification skipped - preference disabled', [
                    'notification_id' => $this->notification->id,
                ]);
                $this->notification->update(['status' => 'failed', 'failed_reason' => 'Preference disabled']);
                return;
            }

            // Check quiet hours (skip real-time channels during quiet hours)
            if ($preference->isInQuietHours() && in_array($this->notification->channel, ['push', 'sms'])) {
                Log::info('Notification deferred - quiet hours', [
                    'notification_id' => $this->notification->id,
                ]);
                // Re-queue for after quiet hours
                $this->release(300); // 5 minutes
                return;
            }

            // Get appropriate adapter and send
            $adapter = ChannelAdapterFactory::make($this->notification->channel);
            $result = $adapter->send($this->notification);

            if ($result) {
                $this->notification->markAsSent();
                Log::info('Notification sent successfully', [
                    'notification_id' => $this->notification->id,
                    'channel' => $this->notification->channel,
                ]);
            } else {
                throw new \Exception('Adapter returned false');
            }

        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Update retry count and status
            $this->notification->update([
                'retry_count' => $this->attempts(),
            ]);

            // If this is the final attempt, mark as failed and move to dead letter
            if ($this->attempts() >= $this->tries) {
                $this->notification->markAsFailed($e->getMessage());
                $this->sendToDeadLetter($e);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job permanently failed', [
            'notification_id' => $this->notification->id,
            'error' => $exception->getMessage(),
            'total_attempts' => $this->attempts(),
        ]);

        // Mark as failed
        $this->notification->markAsFailed($exception->getMessage());

        // Send to dead letter queue
        $this->sendToDeadLetter($exception);
    }

    /**
     * Send failed notification to dead letter queue and alert admin
     */
    private function sendToDeadLetter(\Throwable $exception): void
    {
        try {
            // Store in dead_letter_notifications table or cache
            $deadLetterData = [
                'notification_id' => $this->notification->id,
                'user_id' => $this->notification->user_id,
                'type' => $this->notification->type,
                'channel' => $this->notification->channel,
                'error' => $exception->getMessage(),
                'failed_at' => now(),
                'payload' => $this->notification->payload,
            ];

            // Add to dead letter list (could be a database table)
            $deadLetters = cache()->get('notifications:dead_letter', []);
            $deadLetters[] = $deadLetterData;
            cache()->put('notifications:dead_letter', $deadLetters, now()->addDays(7));

            // Alert admin about permanent failure
            // This would typically dispatch another notification to admins
            Log::alert('Notification moved to dead letter queue', $deadLetterData);

        } catch (\Exception $e) {
            Log::critical('Failed to process dead letter', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
