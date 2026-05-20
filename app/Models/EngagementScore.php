<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EngagementScore extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'learner_id', 'course_id', 'week_number',
        'login_consistency_score',
        'content_completion_score',
        'assessment_activity_score',
        'forum_participation_score',
        'pacing_score',
        'live_session_score',
        'engagement_score',
        'previous_week_score',
        'score_delta',
        'component_breakdown',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'login_consistency_score'   => 'float',
            'content_completion_score'  => 'float',
            'assessment_activity_score' => 'float',
            'forum_participation_score' => 'float',
            'pacing_score'              => 'float',
            'live_session_score'        => 'float',
            'engagement_score'          => 'float',
            'previous_week_score'       => 'float',
            'score_delta'               => 'float',
            'component_breakdown'       => 'array',
            'computed_at'               => 'datetime',
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

    /**
     * Weighted engagement formula:
     * E = 0.15·L + 0.25·C + 0.20·A + 0.15·F + 0.15·P + 0.10·S
     */
    public static function computeFromComponents(
        float $L, float $C, float $A, float $F, float $P, float $S
    ): float {
        return round(
            (0.15 * $L) + (0.25 * $C) + (0.20 * $A) + (0.15 * $F) + (0.15 * $P) + (0.10 * $S),
            2
        );
    }
}
