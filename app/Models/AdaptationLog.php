<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdaptationLog extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'adaptation_log';

    protected $fillable = [
        'id', 'student_id', 'chunk_id', 'adapted_text', 'original_text',
        'profile_snapshot', 'instructor_settings_snapshot', 'flagged',
        'flagged_by', 'feedback_rating', 'feedback_complexity',
    ];

    protected function casts(): array
    {
        return [
            'profile_snapshot' => 'array',
            'instructor_settings_snapshot' => 'array',
            'flagged' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(ContentChunk::class, 'chunk_id');
    }

    public function flaggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }
}
