<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentChunk extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id', 'content_id', 'content_source', 'chunk_index', 'chunk_text', 'chunk_type',
        'semantic_role', 'key_terms', 'lesson_position_pct',
    ];

    protected $casts = [
        'key_terms' => 'array',
    ];

    public function lessonPage(): BelongsTo
    {
        return $this->belongsTo(LessonPage::class, 'content_id');
    }

    public function courseMaterial(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class, 'content_id');
    }

    public function adaptationLogs(): HasMany
    {
        return $this->hasMany(AdaptationLog::class, 'chunk_id');
    }

    public function isMaterialChunk(): bool
    {
        return $this->content_source === 'course_material';
    }
}
