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
        'default_mark', 'shuffle_answers', 'multiple_answers', 'correct_answer',
        'penalty', 'hints', 'matching_pairs',
    ];

    protected function casts(): array
    {
        return [
            'shuffle_answers'  => 'boolean',
            'multiple_answers' => 'boolean',
            'hints'            => 'array',
            'matching_pairs'   => 'array',
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
