<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileDriftLog extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'learner_id', 'course_id', 'detected_at_week',
        'declared_profile', 'observed_pattern', 'drift_direction',
        'drift_severity', 'drift_confirmed_at', 'facilitator_alerted',
        'resolution', 'resolved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'facilitator_alerted' => 'boolean',
            'drift_confirmed_at'  => 'datetime',
            'resolved_at'         => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
