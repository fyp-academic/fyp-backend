<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'course_id', 'user_id', 'title', 'description', 'location', 'event_type',
        'all_day', 'start_at', 'end_at',
        'recurrence_freq', 'recurrence_interval', 'recurrence_until', 'color',
    ];

    protected function casts(): array
    {
        return [
            'all_day'             => 'boolean',
            'start_at'            => 'datetime',
            'end_at'             => 'datetime',
            'recurrence_until'    => 'date',
            'recurrence_interval' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
