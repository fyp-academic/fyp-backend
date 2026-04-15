<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerProfile extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'learner_id', 'course_id', 'primary_profile', 'secondary_profile',
        'is_mixed_profile', 'mixed_blend_primary', 'mixed_blend_secondary',
        'h_score', 'a_score', 't_score', 'c_score', 'declared_preferences',
        'lms_flags', 'pulse_consent', 'pulse_consent_at', 'drift_flag',
        'drift_weeks_count', 'drift_flagged_at', 'profile_note',
    ];

    protected function casts(): array
    {
        return [
            'is_mixed_profile'      => 'boolean',
            'declared_preferences'  => 'array',
            'lms_flags'             => 'array',
            'pulse_consent'         => 'boolean',
            'pulse_consent_at'      => 'datetime',
            'drift_flag'            => 'boolean',
            'drift_flagged_at'      => 'datetime',
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
