<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'question_id', 'match_group', 'text', 'answer_type', 'answer_image_url',
        'grade_fraction', 'min_value', 'max_value', 'feedback', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'grade_fraction' => 'decimal:4',
            'min_value'      => 'decimal:4',
            'max_value'      => 'decimal:4',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'question_id');
    }
}
