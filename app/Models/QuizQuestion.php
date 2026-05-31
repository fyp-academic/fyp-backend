<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizQuestion extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'activity_id', 'course_id', 'type', 'question_text', 'category',
        'default_mark', 'grade_method', 'shuffle_answers', 'multiple_answers',
        'choice_numbering', 'correct_answer', 'case_sensitive', 'use_fuzzy_matching',
        'penalty', 'hints', 'matching_pairs', 'drag_drop_config', 'background_image',
        'tolerance_type', 'tolerance_value', 'unit_handling', 'units',
        'requires_manual_grading', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'shuffle_answers'       => 'boolean',
            'multiple_answers'      => 'boolean',
            'case_sensitive'        => 'boolean',
            'use_fuzzy_matching'    => 'boolean',
            'requires_manual_grading' => 'boolean',
            'is_active'             => 'boolean',
            'hints'                 => 'array',
            'matching_pairs'        => 'array',
            'drag_drop_config'      => 'array',
            'units'                 => 'array',
            'tolerance_value'       => 'decimal:4',
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

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class, 'question_id')->orderBy('sort_order');
    }
}
