<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuizAttemptResponse extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'attempt_id', 'question_id', 'answer_id',
        'response_text', 'marks_awarded', 'marks_max', 'feedback',
    ];

    protected function casts(): array
    {
        return [
            'marks_awarded' => 'float',
            'marks_max'     => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(QuizAnswer::class, 'answer_id');
    }
}
