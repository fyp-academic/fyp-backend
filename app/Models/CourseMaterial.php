<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseMaterial extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'activity_id', 'course_id', 'uploaded_by',
        'title', 'type', 'file_path', 'url', 'mime_type', 'file_size',
        'extracted_text', 'processed_at', 'word_count',
        'processing_status', 'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
            'file_size'    => 'integer',
            'word_count'   => 'integer',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────

    public function scopeProcessed($query)
    {
        return $query->where('processing_status', 'completed');
    }

    public function scopeForActivity($query, string $activityId)
    {
        return $query->where('activity_id', $activityId);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    public function hasExtractedText(): bool
    {
        return !empty($this->extracted_text);
    }

    public function isYouTube(): bool
    {
        return $this->type === 'youtube';
    }
}
