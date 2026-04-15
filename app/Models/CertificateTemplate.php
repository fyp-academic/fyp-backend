<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'activity_id', 'course_id', 'name',
        'body_html', 'orientation', 'required_activities',
        'min_grade', 'expiry_days',
    ];

    protected function casts(): array
    {
        return [
            'required_activities' => 'array',
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

    public function issues(): HasMany
    {
        return $this->hasMany(CertificateIssue::class, 'certificate_id');
    }
}
