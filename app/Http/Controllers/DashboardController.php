<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Course;
use App\Models\College;
use App\Models\DegreeProgramme;
use App\Models\Category;
use App\Models\Enrollment;
use App\Models\Activity;
use App\Models\StudentGrade;
use App\Models\Notification as NotificationModel;
use App\Models\DashboardEngagement;
use App\Policies\RolePolicy;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard
     * Unified dashboard endpoint that returns data based on user role.
     * - Admin: Full platform statistics
     * - Instructor: Programme-scoped data (courses, students in their programmes)
     * - Student: Personal learning data
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return match ($user->role) {
            'admin' => $this->getAdminDashboard($user),
            'instructor' => $this->getInstructorDashboard($user),
            'student' => $this->getStudentDashboard($user),
            default => response()->json(['message' => 'Invalid role'], 403),
        };
    }

    /**
     * GET /api/v1/dashboard/admin
     * Platform-wide statistics for admin users.
     */
    public function admin(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!RolePolicy::isAdmin($user)) {
            return response()->json(['message' => 'Forbidden. Admin access required.'], 403);
        }

        return $this->getAdminDashboard($user);
    }

    /**
     * GET /api/v1/dashboard/instructor
     * Programme-scoped dashboard for instructors.
     */
    public function instructor(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!RolePolicy::isInstructor($user)) {
            return response()->json(['message' => 'Forbidden. Instructor access required.'], 403);
        }

        return $this->getInstructorDashboard($user);
    }

    /**
     * GET /api/v1/dashboard/student
     * Personal dashboard for students.
     */
    public function student(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!RolePolicy::isStudent($user)) {
            return response()->json(['message' => 'Forbidden. Student access required.'], 403);
        }

        return $this->getStudentDashboard($user);
    }

    /**
     * Get admin dashboard data.
     */
    private function getAdminDashboard(User $user): JsonResponse
    {
        // Count statistics
        $totalUsers = User::count();
        $totalStudents = User::where('role', 'student')->count();
        $totalInstructors = User::where('role', 'instructor')->count();
        $totalColleges = College::count();
        $totalDegreeProgrammes = DegreeProgramme::count();
        $totalCourses = Course::count();
        $totalCategories = Category::count();
        $totalEnrollments = Enrollment::count();
        $activeCourses = Course::where('status', 'active')->count();

        // Recent activity
        $recentUsers = User::select('id', 'name', 'email', 'role', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentCourses = Course::with(['instructor', 'category'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Notifications
        $notifications = NotificationModel::where('user_id', $user->id)
            ->orderByRaw("CASE WHEN read = false THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'role' => 'admin',
            'stats' => [
                'total_users' => $totalUsers,
                'total_students' => $totalStudents,
                'total_instructors' => $totalInstructors,
                'total_colleges' => $totalColleges,
                'total_degree_programmes' => $totalDegreeProgrammes,
                'total_courses' => $totalCourses,
                'total_categories' => $totalCategories,
                'total_enrollments' => $totalEnrollments,
                'active_courses' => $activeCourses,
            ],
            'recent_users' => $recentUsers,
            'recent_courses' => $recentCourses,
            'recent_notifications' => $notifications,
            'system_health' => 'ok',
            'permissions' => [
                'can_manage_colleges' => true,
                'can_manage_degree_programmes' => true,
                'can_manage_courses' => true,
                'can_manage_categories' => true,
                'can_manage_instructors' => true,
                'can_manage_students' => true,
                'can_view_all_data' => true,
            ],
        ]);
    }

    /**
     * Get instructor dashboard data - scoped to their assigned programmes.
     */
    private function getInstructorDashboard(User $user): JsonResponse
    {
        $assignedProgrammeIds = RolePolicy::getAssignedProgrammeIds($user);

        if (empty($assignedProgrammeIds)) {
            return response()->json([
                'role' => 'instructor',
                'message' => 'You are not assigned to any degree programmes. Please contact an administrator.',
                'stats' => [
                    'assigned_programmes' => 0,
                    'active_courses' => 0,
                    'total_enrollments' => 0,
                    'total_students' => 0,
                ],
                'permissions' => [
                    'can_manage_colleges' => false,
                    'can_manage_degree_programmes' => false,
                    'can_manage_courses' => false,
                    'can_manage_categories' => false,
                    'can_manage_instructors' => false,
                    'can_manage_students' => false,
                    'assigned_programme_ids' => [],
                ],
            ]);
        }

        // Get assigned programmes with details
        $assignedProgrammes = DegreeProgramme::with('college')
            ->whereIn('id', $assignedProgrammeIds)
            ->get();

        // Get all courses in assigned programmes OR courses created by instructor
        $coursesQuery = Course::where(function ($q) use ($user, $assignedProgrammeIds) {
            $q->where('instructor_id', $user->id)
              ->orWhereHas('degreeProgrammes', function ($subQ) use ($assignedProgrammeIds) {
                  $subQ->whereIn('degree_programmes.id', $assignedProgrammeIds);
              });
        });

        $courses = $coursesQuery->with('sections.activities')->get();
        $courseIds = $courses->pluck('id');

        // Get students in assigned programmes
        $students = User::where('role', 'student')
            ->whereIn('degree_programme_id', $assignedProgrammeIds)
            ->with('degreeProgramme')
            ->get();

        $studentIds = $students->pluck('id');

        // Count statistics
        $totalEnrollments = Enrollment::whereIn('course_id', $courseIds)
            ->where('role', 'student')
            ->count();

        $activeCount = $courses->where('status', 'active')->count();

        // Engagement data
        $engagement = DashboardEngagement::whereIn('course_id', $courseIds)
            ->orderBy('week_of', 'desc')
            ->limit(7)
            ->get()
            ->map(fn ($e) => [
                'day' => $e->day_label,
                'active' => $e->active_students,
                'submissions' => $e->submissions,
            ]);

        // Pending grades
        $pendingGrades = StudentGrade::whereHas('gradeItem', function ($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds);
            })
            ->whereIn('status', ['submitted', 'not_submitted'])
            ->count();

        // Notifications
        $notifications = NotificationModel::where('user_id', $user->id)
            ->orderByRaw("CASE WHEN read = false THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'role' => 'instructor',
            'stats' => [
                'assigned_programmes' => $assignedProgrammes->count(),
                'active_courses' => $activeCount,
                'total_courses' => $courses->count(),
                'total_enrollments' => $totalEnrollments,
                'total_students' => $students->count(),
                'pending_grading_count' => $pendingGrades,
            ],
            'assigned_programmes' => $assignedProgrammes,
            'my_courses' => $courses,
            'my_students' => $students->take(10), // Limit to recent 10 for dashboard
            'weekly_engagement' => $engagement,
            'recent_notifications' => $notifications,
            'permissions' => [
                'can_manage_colleges' => false,
                'can_manage_degree_programmes' => false,
                'can_manage_courses' => true,
                'can_manage_categories' => false,
                'can_manage_instructors' => false,
                'can_manage_students' => true,
                'can_view_assigned_data_only' => true,
                'assigned_programme_ids' => $assignedProgrammeIds,
            ],
        ]);
    }

    /**
     * Get student dashboard data.
     */
    private function getStudentDashboard(User $user): JsonResponse
    {
        // Get student's enrolled courses
        $enrollments = Enrollment::where('user_id', $user->id)
            ->with('course.sections.activities')
            ->get();

        $enrolledCourses = $enrollments->map(fn ($e) => [
            'course' => $e->course,
            'role' => $e->role,
            'progress' => $e->progress,
            'enrolled_date' => $e->enrolled_date,
        ]);

        $overallProgress = $enrollments->count() > 0
            ? round($enrollments->avg('progress'), 1)
            : 0;

        $courseIds = $enrollments->pluck('course_id');
        $upcomingDues = Activity::whereIn('course_id', $courseIds)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'name', 'type', 'due_date', 'course_id']);

        $notifications = NotificationModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Get student's degree programme info
        $degreeProgramme = null;
        if ($user->degree_programme_id) {
            $degreeProgramme = DegreeProgramme::with('college')
                ->find($user->degree_programme_id);
        }

        // Available courses for enrollment (in student's programme)
        $availableCourses = collect();
        if ($user->degree_programme_id) {
            $enrolledCourseIds = $enrollments->pluck('course_id')->toArray();
            $availableCourses = Course::whereHas('degreeProgrammes', function ($q) use ($user) {
                $q->where('degree_programmes.id', $user->degree_programme_id);
            })
            ->where('visibility', 'shown')
            ->where('status', 'active')
            ->whereNotIn('id', $enrolledCourseIds)
            ->with(['instructor', 'category'])
            ->limit(5)
            ->get();
        }

        return response()->json([
            'role' => 'student',
            'stats' => [
                'enrolled_courses' => $enrollments->count(),
                'overall_progress' => $overallProgress,
                'upcoming_activities' => $upcomingDues->count(),
            ],
            'enrolled_courses' => $enrolledCourses,
            'overall_progress' => $overallProgress,
            'upcoming_due_dates' => $upcomingDues,
            'recent_notifications' => $notifications,
            'degree_programme' => $degreeProgramme,
            'available_courses' => $availableCourses,
            'permissions' => [
                'can_manage_colleges' => false,
                'can_manage_degree_programmes' => false,
                'can_manage_courses' => false,
                'can_manage_categories' => false,
                'can_manage_instructors' => false,
                'can_manage_students' => false,
                'can_self_enroll' => true,
                'can_view_own_data_only' => true,
            ],
        ]);
    }
}
