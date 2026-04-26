<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Course;
use App\Models\DegreeProgramme;
use Illuminate\Database\Eloquent\Model;

class RolePolicy
{
    /**
     * Check if user is admin.
     */
    public static function isAdmin(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Check if user is instructor.
     */
    public static function isInstructor(User $user): bool
    {
        return $user->role === 'instructor';
    }

    /**
     * Check if user is student.
     */
    public static function isStudent(User $user): bool
    {
        return $user->role === 'student';
    }

    /**
     * Check if user has admin or instructor role.
     */
    public static function isAdminOrInstructor(User $user): bool
    {
        return in_array($user->role, ['admin', 'instructor'], true);
    }

    /**
     * Get degree programme IDs the instructor is assigned to.
     * Returns empty array for admin (access to all) or non-instructors.
     */
    public static function getAssignedProgrammeIds(User $user): array
    {
        if (self::isAdmin($user)) {
            return []; // Empty means all
        }

        if (self::isInstructor($user)) {
            // Get assignments from degree_programme_instructor table (used by assignInstructors endpoint)
            $assignedIds = $user->assignedDegreeProgrammes()->pluck('degree_programmes.id')->toArray();

            // Also check instructor_degree_programme table if user has an instructor profile
            if ($user->instructor) {
                $instructorIds = $user->instructor->degreeProgrammes()->pluck('degree_programmes.id')->toArray();
                $assignedIds = array_unique(array_merge($assignedIds, $instructorIds));
            }

            return $assignedIds;
        }

        if (self::isStudent($user) && $user->degree_programme_id) {
            return [$user->degree_programme_id];
        }

        return [];
    }

    /**
     * Check if instructor can access a specific degree programme.
     */
    public static function canAccessProgramme(User $user, string $programmeId): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        $assignedIds = self::getAssignedProgrammeIds($user);
        return in_array($programmeId, $assignedIds, true);
    }

    /**
     * Check if user can access a specific course based on their role.
     * - Admin: can access all courses
     * - Instructor: can access courses in their assigned programmes or courses they created
     * - Student: can access courses in their degree programme
     */
    public static function canAccessCourse(User $user, Course $course): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (self::isInstructor($user)) {
            // Instructors can access courses they created
            if ($course->instructor_id === $user->id) {
                return true;
            }

            // Or courses in their assigned programmes
            $assignedProgrammeIds = self::getAssignedProgrammeIds($user);
            $courseProgrammeIds = $course->degreeProgrammes()->pluck('degree_programmes.id')->toArray();

            return !empty(array_intersect($assignedProgrammeIds, $courseProgrammeIds));
        }

        if (self::isStudent($user)) {
            // Students can access courses in their degree programme
            if (!$user->degree_programme_id) {
                return false;
            }

            return $course->degreeProgrammes()
                ->where('degree_programmes.id', $user->degree_programme_id)
                ->exists();
        }

        return false;
    }

    /**
     * Check if user can manage a specific course (edit, delete, enroll students).
     * - Admin: can manage all courses
     * - Instructor: can manage courses they created or courses in their assigned programmes
     * - Student: cannot manage courses
     */
    public static function canManageCourse(User $user, Course $course): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (self::isInstructor($user)) {
            // Instructors can manage courses they created
            if ($course->instructor_id === $user->id) {
                return true;
            }

            // Or courses in their assigned programmes
            $assignedProgrammeIds = self::getAssignedProgrammeIds($user);
            $courseProgrammeIds = $course->degreeProgrammes()->pluck('degree_programmes.id')->toArray();

            return !empty(array_intersect($assignedProgrammeIds, $courseProgrammeIds));
        }

        return false;
    }

    /**
     * Check if user can create courses.
     * - Admin: can create courses for any programme
     * - Instructor: can create courses only for their assigned programmes
     * - Student: cannot create courses
     */
    public static function canCreateCourse(User $user, ?array $programmeIds = null): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (self::isInstructor($user)) {
            if ($programmeIds === null || empty($programmeIds)) {
                // Allow if they have at least one assigned programme
                return !empty(self::getAssignedProgrammeIds($user));
            }

            $assignedIds = self::getAssignedProgrammeIds($user);
            return !empty(array_intersect($assignedIds, $programmeIds));
        }

        return false;
    }

    /**
     * Check if user can manage students.
     * - Admin: can manage all students
     * - Instructor: can manage students in their assigned programmes
     * - Student: cannot manage students
     */
    public static function canManageStudent(User $user, ?User $student = null): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (self::isInstructor($user)) {
            if ($student === null) {
                // General check - can they manage any students?
                return !empty(self::getAssignedProgrammeIds($user));
            }

            // Check if student belongs to one of instructor's programmes
            $assignedProgrammeIds = self::getAssignedProgrammeIds($user);
            return in_array($student->degree_programme_id, $assignedProgrammeIds, true);
        }

        return false;
    }

    /**
     * Check if user can view students.
     * - Admin: can view all students
     * - Instructor: can view students in their assigned programmes
     * - Student: cannot view students (unless it's themselves)
     */
    public static function canViewStudents(User $user): bool
    {
        if (self::isAdmin($user)) {
            return true;
        }

        if (self::isInstructor($user)) {
            return !empty(self::getAssignedProgrammeIds($user));
        }

        return false;
    }

    /**
     * Check if user can manage degree programmes.
     * - Admin: can manage all degree programmes
     * - Instructor: cannot manage degree programmes (but can view assigned ones)
     * - Student: cannot manage degree programmes
     */
    public static function canManageDegreeProgrammes(User $user): bool
    {
        return self::isAdmin($user);
    }

    /**
     * Check if user can manage colleges.
     * - Admin: can manage all colleges
     * - Instructor: cannot manage colleges
     * - Student: cannot manage colleges
     */
    public static function canManageColleges(User $user): bool
    {
        return self::isAdmin($user);
    }

    /**
     * Check if user can manage categories.
     * - Admin: can manage all categories
     * - Instructor: cannot manage categories
     * - Student: cannot manage categories
     */
    public static function canManageCategories(User $user): bool
    {
        return self::isAdmin($user);
    }

    /**
     * Check if user can manage instructors.
     * - Admin: can manage all instructors
     * - Instructor: cannot manage instructors
     * - Student: cannot manage instructors
     */
    public static function canManageInstructors(User $user): bool
    {
        return self::isAdmin($user);
    }

    /**
     * Scope a query based on user's role for degree programmes.
     */
    public static function scopeDegreeProgrammes(User $user, $query)
    {
        if (self::isAdmin($user)) {
            return $query;
        }

        $assignedIds = self::getAssignedProgrammeIds($user);

        if (!empty($assignedIds)) {
            return $query->whereIn('id', $assignedIds);
        }

        // If student has no programme or instructor has no assignments, return nothing
        return $query->whereRaw('1 = 0');
    }

    /**
     * Scope a query based on user's role for courses.
     */
    public static function scopeCourses(User $user, $query)
    {
        if (self::isAdmin($user)) {
            return $query;
        }

        if (self::isInstructor($user)) {
            $assignedProgrammeIds = self::getAssignedProgrammeIds($user);

            return $query->where(function ($q) use ($user, $assignedProgrammeIds) {
                // Courses they created
                $q->where('instructor_id', $user->id);

                // Or courses in their assigned programmes
                if (!empty($assignedProgrammeIds)) {
                    $q->orWhereHas('degreeProgrammes', function ($subQ) use ($assignedProgrammeIds) {
                        $subQ->whereIn('degree_programmes.id', $assignedProgrammeIds);
                    });
                }
            });
        }

        if (self::isStudent($user) && $user->degree_programme_id) {
            return $query->whereHas('degreeProgrammes', function ($q) use ($user) {
                $q->where('degree_programmes.id', $user->degree_programme_id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Scope a query based on user's role for users (students).
     */
    public static function scopeStudents(User $user, $query)
    {
        if (self::isAdmin($user)) {
            return $query->where('role', 'student');
        }

        if (self::isInstructor($user)) {
            $assignedProgrammeIds = self::getAssignedProgrammeIds($user);

            if (empty($assignedProgrammeIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where('role', 'student')
                ->whereIn('degree_programme_id', $assignedProgrammeIds);
        }

        return $query->whereRaw('1 = 0');
    }
}
