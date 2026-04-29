<?php

namespace App\Console\Commands;

use App\Adapters\EmailAdapter;
use App\Models\Notification;
use App\Models\NotificationDigest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendNotificationDigests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-digests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send pending notification digests that are due';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Processing notification digests...');

        // Find all digests that are due
        $digests = NotificationDigest::where('next_send_at', '<=', now())
            ->whereNotNull('pending_ids')
            ->whereRaw('JSON_LENGTH(pending_ids) > 0')
            ->with('user')
            ->get();

        if ($digests->isEmpty()) {
            $this->info('No digests to process.');
            return self::SUCCESS;
        }

        $this->info("Found {$digests->count()} digests to process");
        $successCount = 0;
        $failCount = 0;

        foreach ($digests as $digest) {
            try {
                $this->processDigest($digest);
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                Log::error('Failed to process digest', [
                    'digest_id' => $digest->id,
                    'user_id' => $digest->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Digest processing complete: {$successCount} success, {$failCount} failed");

        return self::SUCCESS;
    }

    /**
     * Process a single digest.
     */
    private function processDigest(NotificationDigest $digest): void
    {
        $pendingIds = $digest->pending_ids ?? [];

        if (empty($pendingIds)) {
            // No pending notifications, just update next send time
            $digest->scheduleNextSend();
            return;
        }

        // Fetch pending notifications
        $notifications = Notification::whereIn('id', $pendingIds)
            ->where('status', 'pending')
            ->get();

        if ($notifications->isEmpty()) {
            // All notifications already processed
            $digest->clearPending();
            $digest->scheduleNextSend();
            return;
        }

        // Send digest based on channel
        if ($digest->channel === 'email') {
            $this->sendEmailDigest($digest, $notifications);
        } else {
            // For in-app digests, just mark as sent
            foreach ($notifications as $notification) {
                $notification->markAsSent();
            }
        }

        // Update digest state
        $digest->clearPending();
        $digest->scheduleNextSend();

        Log::info('Digest sent', [
            'digest_id' => $digest->id,
            'user_id' => $digest->user_id,
            'channel' => $digest->channel,
            'count' => $notifications->count(),
        ]);
    }

    /**
     * Send email digest with multiple notifications.
     */
    private function sendEmailDigest(NotificationDigest $digest, $notifications): void
    {
        $user = $digest->user;

        if (!$user || !$user->email) {
            throw new \Exception('User has no email address');
        }

        // Build digest email
        $emailData = [
            'subject' => "Your {$digest->frequency} Notification Digest - " . config('app.name'),
            'notifications' => $notifications->map(fn($n) => [
                'title' => $n->title,
                'body' => $n->body,
                'type' => $n->type,
                'created_at' => $n->created_at->format('M j, Y g:i A'),
            ])->toArray(),
            'count' => $notifications->count(),
            'frequency' => $digest->frequency,
        ];

        // Send using Laravel Mail
        \Illuminate\Support\Facades\Mail::send('emails.digest', $emailData, function ($message) use ($user, $emailData) {
            $message->to($user->email)
                ->subject($emailData['subject'])
                ->from(config('mail.from.address'), config('mail.from.name'));
        });

        // Mark notifications as sent
        foreach ($notifications as $notification) {
            $notification->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        }
    }
}
