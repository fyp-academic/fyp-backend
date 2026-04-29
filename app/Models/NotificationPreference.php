<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'notification_type',
        'channel',
        'enabled',
        'digest_mode',
        'quiet_start',
        'quiet_end',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'quiet_start' => 'datetime:H:i',
            'quiet_end' => 'datetime:H:i',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if notification should be sent based on quiet hours
     */
    public function isInQuietHours(): bool
    {
        if (!$this->quiet_start || !$this->quiet_end) {
            return false;
        }

        $now = now()->format('H:i:s');
        $start = $this->quiet_start->format('H:i:s');
        $end = $this->quiet_end->format('H:i:s');

        if ($start < $end) {
            return $now >= $start && $now <= $end;
        }

        // Crosses midnight (e.g., 23:00 - 07:00)
        return $now >= $start || $now <= $end;
    }

    /**
     * Check if notification should be sent instantly or batched
     */
    public function shouldBatch(): bool
    {
        return in_array($this->digest_mode, ['daily', 'weekly'], true);
    }
}
