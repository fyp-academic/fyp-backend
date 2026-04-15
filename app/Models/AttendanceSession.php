<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'activity_id', 'course_id', 'title', 'description',
        'session_date', 'duration_minutes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class, 'session_id');
    }
}
