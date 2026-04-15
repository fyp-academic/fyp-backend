<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BehavioralSignal extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'behavioral_signals';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'learner_id', 'course_id', 'week_number',
        'login_frequency', 'time_on_task_hours', 'content_completion_rate',
        'quiz_attempt_count', 'quiz_available_count', 'submission_timing',
        'forum_post_count', 'forum_posts_required', 'navigation_pattern',
        'normalised_values', 'colour_flags', 'raw_data', 'computed_at',
    ];

    protected function casts(): array
    {
        return [
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
