<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'name', 'short_name', 'description', 'category_id', 'category_name',
        'college_id', 'instructor_id', 'instructor_name', 'enrolled_students', 'status', 'visibility',
        'format', 'start_date', 'end_date', 'language', 'tags', 'max_students', 'image',
        // Course-settings fields
        'id_number', 'summary', 'group_mode', 'self_enrollment', 'enrollment_key',
        'enrollment_start_date', 'enrollment_end_date', 'grade_display_type',
        'grade_passing_grade', 'completion_tracking', 'max_upload_size',
        'allowed_file_types', 'show_gradebook', 'show_activity_reports', 'force_download',
    ];

    protected function casts(): array
    {
        return [
            'tags'                  => 'array',
            // date:Y-m-d so <input type="date"> receives a clean value, not an ISO datetime
            'start_date'            => 'date:Y-m-d',
            'end_date'              => 'date:Y-m-d',
            'enrollment_start_date' => 'date:Y-m-d',
            'enrollment_end_date'   => 'date:Y-m-d',
            'self_enrollment'       => 'boolean',
            'completion_tracking'   => 'boolean',
            'show_gradebook'        => 'boolean',
            'show_activity_reports' => 'boolean',
            'force_download'        => 'boolean',
            'grade_passing_grade'   => 'integer',
            'max_upload_size'       => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    public function degreeProgrammes(): BelongsToMany
    {
        return $this->belongsToMany(DegreeProgramme::class, 'course_degree_programme');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function gradeItems(): HasMany
    {
        return $this->hasMany(GradeItem::class);
    }
}
