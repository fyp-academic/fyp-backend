<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'channel',
        'title',
        'body',
        'payload',
        'dedup_key',
        'status',
        'read_at',
        'sent_at',
        'failed_reason',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => 'string',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        $this->update([
            'status' => 'read',
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as sent
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark notification as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update(['status' => 'delivered']);
    }

    /**
     * Mark notification as failed with reason
     */
    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => 'failed',
            'failed_reason' => $reason,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Check if notification can be retried
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->retry_count < $maxRetries && $this->status === 'failed';
    }

    /**
     * Scope for pending notifications
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for user's unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereIn('status', ['sent', 'delivered']);
    }

    /**
     * Scope for failed notifications that can be retried
     */
    public function scopeRetryable($query, int $maxRetries = 3)
    {
        return $query->where('status', 'failed')
            ->where('retry_count', '<', $maxRetries);
    }
}
