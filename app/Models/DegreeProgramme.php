<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DegreeProgramme extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'college_id', 'name', 'code', 'description',
    ];

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_degree_programme');
    }

    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'degree_programme_instructor', 'degree_programme_id', 'instructor_id');
    }
}
