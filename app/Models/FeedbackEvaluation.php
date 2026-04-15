<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackEvaluation extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'intervention_id', 'learner_id', 'course_id', 'evaluated_at_week',
        'score_before', 'score_at_t7', 'score_at_t14',
        'score_delta_t7', 'score_delta_t14',
        'recovery_threshold_met', 'outcome_label',
        're_threshold_adjustment', 'model_notes',
    ];

    protected function casts(): array
    {
        return [
            'recovery_threshold_met'  => 'boolean',
            're_threshold_adjustment' => 'array',
        ];
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(Intervention::class);
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
