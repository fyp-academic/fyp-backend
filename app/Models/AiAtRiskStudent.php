<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAtRiskStudent extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'ai_at_risk_students';

    protected $fillable = [
        'id', 'course_id', 'student_id', 'student_name', 'progress',
        'last_access', 'missed_activities', 'grade', 'risk_level',
        'ai_recommendation', 'detected_at',
    ];

    protected function casts(): array
    {
        return ['detected_at' => 'date'];
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
