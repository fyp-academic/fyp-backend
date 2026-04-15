<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Intervention extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'learner_id', 'course_id', 'facilitator_id', 'week_number',
        'tier', 'trigger_score', 'profile_type',
        'channel', 'template_id', 'message_body',
        'sent_at', 'cooldown_expires_at',
        'score_at_t7', 'score_at_t14', 'outcome', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sent_at'             => 'datetime',
            'cooldown_expires_at' => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    public function facilitator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitator_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function evaluation(): HasOne
    {
        return $this->hasOne(FeedbackEvaluation::class);
    }
}
