<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PracticalSubmission extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'activity_id', 'student_id', 'course_id',
        'files', 'status', 'started_at', 'submitted_at', 'auto_submitted',
        'grade', 'graded_by', 'graded_at', 'feedback',
    ];

    protected function casts(): array
    {
        return [
            'files'          => 'array',
            'started_at'     => 'datetime',
            'submitted_at'   => 'datetime',
            'graded_at'      => 'datetime',
            'auto_submitted' => 'boolean',
            'grade'          => 'float',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
