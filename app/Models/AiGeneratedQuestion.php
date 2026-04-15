<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneratedQuestion extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'ai_generated_questions';

    protected $fillable = [
        'id', 'course_id', 'activity_id', 'topic', 'question_text',
        'question_type', 'difficulty', 'status', 'generated_at',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
