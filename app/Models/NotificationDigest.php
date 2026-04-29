<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDigest extends Model
{
    protected $fillable = [
        'user_id',
        'channel',
        'frequency',
        'last_sent_at',
        'next_send_at',
        'pending_ids',
    ];

    protected function casts(): array
    {
        return [
            'pending_ids' => 'array',
            'last_sent_at' => 'datetime',
            'next_send_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Add a notification ID to pending batch
     */
    public function addPendingNotification(int $notificationId): void
    {
        $pending = $this->pending_ids ?? [];
        if (!in_array($notificationId, $pending, true)) {
            $pending[] = $notificationId;
            $this->update(['pending_ids' => $pending]);
        }
    }

    /**
     * Clear pending notifications after send
     */
    public function clearPending(): void
    {
        $this->update([
            'pending_ids' => [],
            'last_sent_at' => now(),
        ]);
    }

    /**
     * Check if it's time to send the digest
     */
    public function shouldSend(): bool
    {
        return now()->greaterThanOrEqualTo($this->next_send_at);
    }

    /**
     * Calculate next send time based on frequency
     */
    public function scheduleNextSend(): void
    {
        $next = match($this->frequency) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            default => now()->addDay(),
        };

        $this->update(['next_send_at' => $next]);
    }
}
