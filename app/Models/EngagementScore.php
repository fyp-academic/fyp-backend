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
     * Base weights for the six engagement signals.
     * E = 0.15·L + 0.25·C + 0.20·A + 0.15·F + 0.15·P + 0.10·S
     */
    public const WEIGHTS = [
        'L' => 0.15,  // login consistency
        'C' => 0.25,  // content completion
        'A' => 0.20,  // assessment activity
        'F' => 0.15,  // forum participation
        'P' => 0.15,  // pacing
        'S' => 0.10,  // live session
    ];

    /** Human-readable label per signal key (for instructor UI / confidence). */
    public const SIGNAL_LABELS = [
        'L' => 'login_consistency',
        'C' => 'content_completion',
        'A' => 'assessment_activity',
        'F' => 'forum_participation',
        'P' => 'pacing',
        'S' => 'live_session',
    ];

    /**
     * Weighted engagement formula (legacy, all-signals-present variant).
     * Kept for backward compatibility / callers that have all six values.
     */
    public static function computeFromComponents(
        float $L, float $C, float $A, float $F, float $P, float $S
    ): float {
        [$score] = self::weightedScore(compact('L', 'C', 'A', 'F', 'P', 'S'));
        return $score;
    }

    /**
     * Renormalised weighted score over only the MEASURED (non-null) signals.
     *
     * A null signal means "not applicable / not measurable" for this learner-week
     * (e.g. the course has no forum, or no live session was scheduled). Such
     * signals are excluded and the remaining weights are rescaled to sum to 1 —
     * so the score reflects what was actually measured instead of injecting a
     * misleading default (previously forum→0.5, no-session→100).
     *
     * @param  array<string,float|null>  $components  keyed by signal letter (L,C,A,F,P,S)
     * @return array{0: float, 1: array{measured: string[], absent: string[], confidence: float, weights: array<string,float>, raw: array<string,float|null>}}
     */
    public static function weightedScore(array $components): array
    {
        $measured = [];
        $absent   = [];
        $weightSum = 0.0;

        foreach (self::WEIGHTS as $key => $weight) {
            $value = $components[$key] ?? null;
            if ($value === null) {
                $absent[] = self::SIGNAL_LABELS[$key];
            } else {
                $measured[]  = self::SIGNAL_LABELS[$key];
                $weightSum  += $weight;
            }
        }

        $score = 0.0;
        if ($weightSum > 0) {
            foreach (self::WEIGHTS as $key => $weight) {
                $value = $components[$key] ?? null;
                if ($value !== null) {
                    $score += ($weight / $weightSum) * $value;
                }
            }
        }

        $total = count(self::WEIGHTS);
        $meta = [
            'measured'   => $measured,
            'absent'     => $absent,
            'confidence' => $total > 0 ? round(count($measured) / $total, 2) : 0.0,
            'weights'    => self::WEIGHTS,
            'raw'        => $components,
        ];

        return [round($score, 2), $meta];
    }
}
