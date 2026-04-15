<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedbackQuestion extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'activity_id', 'type', 'question_text',
        'options', 'required', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options'  => 'array',
            'required' => 'boolean',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(FeedbackResponse::class, 'question_id');
    }
}
