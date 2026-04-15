<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CognitiveSignal extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'cognitive_signals';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'learner_id', 'course_id', 'week_number',
        'content_revisit_rate', 'revisit_flag',
        'quiz_first_attempt_score', 'quiz_final_attempt_score', 'quiz_learning_delta',
        'discussion_depth_score', 'avg_post_word_count', 'question_count', 'assertion_count',
        'peer_response_rate', 'optional_resource_access_rate', 'feedback_uptake_lag_hours',
        'normalised_values', 'colour_flags', 'raw_data', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'revisit_flag'      => 'boolean',
            'normalised_values' => 'array',
            'colour_flags'      => 'array',
            'raw_data'          => 'array',
            'computed_at'       => 'datetime',
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
