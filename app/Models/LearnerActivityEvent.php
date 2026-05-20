<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearnerActivityEvent extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    // Valid event types
    public const EVENT_TYPES = [
        // Content
        'content_view', 'content_skip',
        // Video
        'video_play', 'video_pause', 'video_seek', 'video_complete',
        // PDF / document
        'pdf_open', 'pdf_scroll', 'material_download',
        // Quiz
        'quiz_start', 'quiz_submit', 'quiz_question_skip',
        // Forum
        'forum_post', 'forum_reply', 'forum_view',
        // Activity
        'activity_complete',
        // Session
        'login', 'logout',
        // Attention / focus
        'page_idle', 'tab_blur', 'tab_focus',
        // Search
        'search',
    ];

    protected $fillable = [
        'id', 'user_id', 'course_id', 'login_session_id',
        'event_type', 'resource_type', 'resource_id',
        'value', 'metadata', 'device_type', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'value'       => 'float',
            'metadata'    => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function loginSession(): BelongsTo
    {
        return $this->belongsTo(LearnerLoginSession::class, 'login_session_id');
    }
}
