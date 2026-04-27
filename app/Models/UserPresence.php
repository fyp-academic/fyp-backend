<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPresence extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'user_presence';

    protected $fillable = [
        'id',
        'user_id',
        'status',
        'last_seen_at',
        'last_active_conversation_id',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user is online
     */
    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    /**
     * Check if user is away (inactive for > 5 minutes)
     */
    public function isAway(): bool
    {
        if ($this->status !== 'online') {
            return false;
        }
        return $this->last_seen_at && $this->last_seen_at->diffInMinutes(now()) > 5;
    }
}
