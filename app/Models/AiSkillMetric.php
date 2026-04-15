<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSkillMetric extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'ai_skill_metrics';

    protected $fillable = [
        'id', 'course_id', 'skill_label', 'score', 'full_mark', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'date'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
