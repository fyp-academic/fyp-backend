<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskScore extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'learner_id', 'course_id', 'week_number', 'profile_type',
        'l1_contribution', 'l2_contribution', 'l3_contribution',
        'primary_score', 'secondary_score', 'final_score',
        'previous_week_score', 'score_delta',
        'tier', 'anomaly_flag', 'signal_breakdown',
        'facilitator_notes_prompt', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'anomaly_flag'     => 'boolean',
            'signal_breakdown' => 'array',
            'computed_at'      => 'datetime',
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
