<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityCompletion extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = false;

    protected $fillable = [
        'id', 'user_id', 'activity_id', 'course_id', 'completion_type', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Compute and return the progress percentage for a given user in a course.
     */
    public static function progressFor(string $userId, string $courseId): float
    {
        $total = Activity::whereHas('section', fn ($q) => $q->where('course_id', $courseId))
            ->where('visible', true)
            ->count();

        if ($total === 0) return 0.0;

        $done = static::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->count();

        return (float) (int) round($done / $total * 100);
    }
}
