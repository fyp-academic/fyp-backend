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
        'id', 'content_id', 'chunk_index', 'chunk_text', 'chunk_type',
    ];

    public function lessonPage(): BelongsTo
    {
        return $this->belongsTo(LessonPage::class, 'content_id');
    }

    public function adaptationLogs(): HasMany
    {
        return $this->hasMany(AdaptationLog::class, 'chunk_id');
    }
}
