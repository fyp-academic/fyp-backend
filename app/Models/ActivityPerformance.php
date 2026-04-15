<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityPerformance extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'activity_performance';

    protected $fillable = [
        'id', 'course_id', 'activity_name', 'avg_score_percentage',
        'grade_max', 'recorded_at',
    ];

    protected function casts(): array
    {
        return ['recorded_at' => 'date'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
