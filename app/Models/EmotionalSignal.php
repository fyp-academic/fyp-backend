<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmotionalSignal extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'emotional_signals';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'learner_id', 'course_id', 'week_number',
        'pulse_confidence', 'pulse_energy', 'pulse_composite',
        'pulse_submitted', 'pulse_submitted_at',
        'mood_drift_score', 'mood_drift_flag',
        'help_seeking_rate', 'messages_to_facilitator', 'forum_questions_asked',
        'feedback_response_lag_hours',
        'voluntary_engagement_rate', 'voluntary_engagement_delta',
        'badge_earned_this_week', 'badge_response_delta',
        'colour_flags', 'raw_data', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'pulse_submitted'       => 'boolean',
            'pulse_submitted_at'    => 'datetime',
            'mood_drift_flag'       => 'boolean',
            'badge_earned_this_week'=> 'boolean',
            'colour_flags'          => 'array',
            'raw_data'              => 'array',
            'computed_at'           => 'datetime',
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
