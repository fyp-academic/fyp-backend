<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonPageProgress extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'lesson_page_progress';

    protected $fillable = [
        'id', 'user_id', 'lesson_page_id', 'activity_id',
        'is_viewed', 'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_viewed' => 'boolean',
            'viewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lessonPage(): BelongsTo
    {
        return $this->belongsTo(LessonPage::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
