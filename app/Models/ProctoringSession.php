<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProctoringSession extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'student_id', 'activity_id', 'course_id',
        'context_type', 'quiz_attempt_id', 'assignment_submission_id',
        'status', 'violation_count', 'is_flagged',
        'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'is_flagged'  => 'boolean',
            'started_at'  => 'datetime',
            'ended_at'    => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(ProctoringViolation::class, 'session_id');
    }
}
