<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'conversation_id', 'parent_id', 'sender_id', 'sender_name',
        'content', 'message_type', 'timestamp', 'read',
        'attachment_path', 'attachment_name', 'attachment_type', 'attachment_size',
        'reactions', 'is_pinned', 'pinned_by', 'pinned_at',
        'deleted_at', 'deleted_by', 'deletion_type', 'original_content',
    ];

    protected function casts(): array
    {
        return [
            'read'            => 'boolean',
            'timestamp'       => 'datetime',
            'reactions'       => 'array',
            'attachment_size' => 'integer',
            'is_pinned'       => 'boolean',
            'deleted_at'      => 'datetime',
            'pinned_at'       => 'datetime',
        ];
    }

    // Message types
    public const TYPE_TEXT = 'text';
    public const TYPE_QUESTION = 'question';
    public const TYPE_ANNOUNCEMENT = 'announcement';
    public const TYPE_RESOURCE = 'resource';

    // Deletion types
    public const DELETE_FOR_ME = 'me';
    public const DELETE_FOR_EVERYONE = 'everyone';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Message::class, 'parent_id')->orderBy('created_at');
    }

    public function statusRecords(): HasMany
    {
        return $this->hasMany(MessageStatus::class);
    }

    public function pinner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ChatReport::class);
    }

    /**
     * Check if message is soft deleted (for everyone)
     */
    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    /**
     * Get display content (respects soft delete)
     */
    public function getDisplayContent(): ?string
    {
        if ($this->isDeleted()) {
            return 'This message was deleted';
        }
        return $this->content;
    }

    /**
     * Check if message was sent by specific user
     */
    public function isSentBy(string $userId): bool
    {
        return $this->sender_id === $userId;
    }

    /**
     * Check if message is within deletion time window (e.g., 10 minutes)
     */
    public function isWithinDeletionWindow(int $minutes = 10): bool
    {
        return $this->created_at->diffInMinutes(now()) <= $minutes;
    }

    /**
     * Get delivery status for a specific user
     */
    public function getStatusForUser(string $userId): ?MessageStatus
    {
        return $this->statusRecords()->where('user_id', $userId)->first();
    }

    /**
     * Mark message as delivered to a user
     */
    public function markDelivered(string $userId): void
    {
        $status = $this->statusRecords()->firstOrCreate(
            ['user_id' => $userId],
            ['id' => \Illuminate\Support\Str::uuid(), 'status' => 'sent', 'sent_at' => now()]
        );

        if ($status->status === 'sent') {
            $status->update([
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);
        }
    }

    /**
     * Mark message as read by a user
     */
    public function markRead(string $userId): void
    {
        $status = $this->statusRecords()->firstOrCreate(
            ['user_id' => $userId],
            ['id' => \Illuminate\Support\Str::uuid(), 'status' => 'sent', 'sent_at' => now()]
        );

        if (in_array($status->status, ['sent', 'delivered'])) {
            $status->update([
                'status' => 'read',
                'delivered_at' => $status->delivered_at ?? now(),
                'read_at' => now(),
            ]);
        }
    }
}
