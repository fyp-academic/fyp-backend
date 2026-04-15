<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardEngagement extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'dashboard_engagement';

    protected $fillable = [
        'id', 'course_id', 'day_label', 'active_students', 'submissions', 'week_of',
    ];

    protected function casts(): array
    {
        return ['week_of' => 'date'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
