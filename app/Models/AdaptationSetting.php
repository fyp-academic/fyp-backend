<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdaptationSetting extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'adaptation_settings';

    protected $fillable = [
        'id', 'course_id', 'topic_id', 'allow_simplification',
        'allow_example_substitution', 'allow_analogies',
        'lock_technical_definitions', 'prevent_assessment_rewrite',
        'min_difficulty', 'max_difficulty', 'ai_confidence_threshold',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'allow_simplification' => 'boolean',
            'allow_example_substitution' => 'boolean',
            'allow_analogies' => 'boolean',
            'lock_technical_definitions' => 'boolean',
            'prevent_assessment_rewrite' => 'boolean',
            'min_difficulty' => 'integer',
            'max_difficulty' => 'integer',
            'ai_confidence_threshold' => 'float',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'topic_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
