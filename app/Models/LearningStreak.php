<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LearningStreak extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'learner_id', 'course_id',
        'current_streak_days', 'longest_streak_days',
        'last_active_date', 'streak_broken_at',
    ];

    protected function casts(): array
    {
        return [
            'last_active_date'  => 'date',
            'streak_broken_at'  => 'datetime',
        ];
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Record an active day. Call this whenever a learner does meaningful work in the course.
     */
    public function recordActivity(): void
    {
        $today = now()->toDateString();

        if ($this->last_active_date && $this->last_active_date->toDateString() === $today) {
            return; // already counted today
        }

        $yesterday = now()->subDay()->toDateString();

        if ($this->last_active_date && $this->last_active_date->toDateString() === $yesterday) {
            $this->current_streak_days++;
        } else {
            if ($this->current_streak_days > 0) {
                $this->streak_broken_at = now();
            }
            $this->current_streak_days = 1;
        }

        if ($this->current_streak_days > $this->longest_streak_days) {
            $this->longest_streak_days = $this->current_streak_days;
        }

        $this->last_active_date = $today;
        $this->save();
    }

    /**
     * Get or create the streak record for a learner/course pair.
     */
    public static function record(string $learnerId, string $courseId): self
    {
        $streak = self::firstOrCreate(
            ['learner_id' => $learnerId, 'course_id' => $courseId],
            ['id' => Str::uuid()->toString()]
        );
        $streak->recordActivity();
        return $streak;
    }
}
