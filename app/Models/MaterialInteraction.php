<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialInteraction extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'material_id', 'student_id', 'course_id',
        'opened_at', 'last_interaction_at', 'total_duration_seconds',
        'open_count', 'completion_percent',
        'video_watch_percent', 'rewatch_count',
        'pdf_scroll_depth_percent', 'downloaded',
    ];

    protected function casts(): array
    {
        return [
            'opened_at'              => 'datetime',
            'last_interaction_at'   => 'datetime',
            'total_duration_seconds' => 'integer',
            'open_count'             => 'integer',
            'completion_percent'     => 'float',
            'video_watch_percent'    => 'float',
            'rewatch_count'          => 'integer',
            'pdf_scroll_depth_percent' => 'float',
            'downloaded'             => 'boolean',
        ];
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(CourseMaterial::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
