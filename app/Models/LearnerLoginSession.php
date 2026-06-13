<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearnerLoginSession extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'user_id', 'started_at', 'ended_at', 'duration_seconds',
        'device_type', 'ip_address', 'user_agent', 'browser', 'os',
        'hour_of_day', 'is_bounce', 'pages_visited',
    ];

    protected function casts(): array
    {
        return [
            'started_at'       => 'datetime',
            'ended_at'         => 'datetime',
            'duration_seconds' => 'integer',
            'hour_of_day'      => 'integer',
            'is_bounce'        => 'boolean',
            'pages_visited'    => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LearnerActivityEvent::class, 'login_session_id');
    }

    public function close(): void
    {
        $this->ended_at         = now();
        $this->duration_seconds = (int) $this->started_at->diffInSeconds(now());
        $this->is_bounce        = $this->duration_seconds < 120;
        $this->save();
    }
}
