<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instructor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'gender',
        'date_of_birth',
        'nationality',
        'phone_number',
        'national_id',
        'profile_photo',
        'staff_id',
        'employment_type',
        'academic_rank',
        'college_id',
        'date_of_employment',
        'highest_qualification',
        'field_of_specialization',
        'awarding_institution',
        'year_of_graduation',
        'bio',
        'office_location',
        'office_hours',
        'account_status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_of_employment' => 'date',
        'year_of_graduation' => 'integer',
    ];

    /**
     * Academic rank options for dropdown.
     */
    public static function academicRanks(): array
    {
        return [
            'tutorial_assistant' => 'Tutorial Assistant',
            'graduate_assistant' => 'Graduate Assistant',
            'assistant_lecturer' => 'Assistant Lecturer',
            'lecturer' => 'Lecturer',
            'senior_lecturer' => 'Senior Lecturer',
            'associate_professor' => 'Associate Professor',
            'professor' => 'Professor',
        ];
    }

    /**
     * Employment type options for dropdown.
     */
    public static function employmentTypes(): array
    {
        return [
            'full-time' => 'Full-time',
            'part-time' => 'Part-time',
            'visiting' => 'Visiting',
        ];
    }

    /**
     * Get the user associated with this instructor.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the college this instructor belongs to.
     */
    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }

    /**
     * Get the degree programmes assigned to this instructor.
     */
    public function degreeProgrammes(): BelongsToMany
    {
        return $this->belongsToMany(DegreeProgramme::class, 'instructor_degree_programme');
    }

    /**
     * Get the courses this instructor teaches.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'instructor_id');
    }

    /**
     * Get academic rank label.
     */
    public function getAcademicRankLabelAttribute(): ?string
    {
        return self::academicRanks()[$this->academic_rank] ?? null;
    }

    /**
     * Get employment type label.
     */
    public function getEmploymentTypeLabelAttribute(): ?string
    {
        return self::employmentTypes()[$this->employment_type] ?? null;
    }

    /**
     * Scope for active instructors.
     */
    public function scopeActive($query)
    {
        return $query->where('account_status', 'active');
    }

    /**
     * Check if instructor can access a specific degree programme.
     */
    public function canAccessProgramme(int $programmeId): bool
    {
        return $this->degreeProgrammes()->where('degree_programme_id', $programmeId)->exists();
    }

    /**
     * Get all student IDs from assigned programmes.
     */
    public function assignedStudentIds(): array
    {
        $programmeIds = $this->degreeProgrammes()->pluck('degree_programmes.id')->toArray();

        if (empty($programmeIds)) {
            return [];
        }

        return User::where('role', 'student')
            ->whereIn('degree_programme_id', $programmeIds)
            ->pluck('id')
            ->toArray();
    }
}
