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
use App\Models\LearnerLoginSession;
use App\Models\LearnerActivityEvent;
use App\Models\EngagementScore;
use App\Models\LearningStreak;
use App\Models\ForumPost;
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
            ->orderByRaw("CASE WHEN read_at IS NULL THEN 0 ELSE 1 END")
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
                    'pending_grading' => 0,
                    'upcoming_deadlines' => 0,
                    'new_enrollments' => 0,
                    'forum_posts' => 0,
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

        // Quick-stat metrics for the dashboard cards.
        $upcomingDeadlines = Activity::whereIn('course_id', $courseIds)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addDays(7)])
            ->count();

        $newEnrollments = Enrollment::whereIn('course_id', $courseIds)
            ->where('role', 'student')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $forumPosts = ForumPost::where('created_at', '>=', now()->subDays(7))
            ->where('user_id', '!=', $user->id)
            ->whereHas('discussion', fn ($q) => $q->whereIn('course_id', $courseIds))
            ->count();

        // Notifications
        $notifications = NotificationModel::where('user_id', $user->id)
            ->orderByRaw("CASE WHEN read_at IS NULL THEN 0 ELSE 1 END")
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
                'pending_grading' => $pendingGrades,
                'upcoming_deadlines' => $upcomingDeadlines,
                'new_enrollments' => $newEnrollments,
                'forum_posts' => $forumPosts,
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
        // Enrollments + sections for completion counts
        $enrollments = Enrollment::where('user_id', $user->id)
            ->with('course.sections')
            ->get();

        $courseIds = $enrollments->pluck('course_id');

        // ── Stats ──────────────────────────────────────────────────────────────
        $enrolledCount = $enrollments->count();

        $lessonsCompleted = StudentGrade::where('user_id', $user->id)
            ->whereIn('status', ['graded', 'completed', 'submitted'])
            ->count();

        $pendingTasks = Activity::whereIn('course_id', $courseIds)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->whereIn('type', ['assignment', 'quiz', 'assessment'])
            ->count();

        // ── Risk signal from latest engagement score ───────────────────────────
        $latestScore = EngagementScore::where('learner_id', $user->id)
            ->orderByDesc('computed_at')
            ->value('engagement_score');
        $riskSignal = ($latestScore === null || $latestScore >= 40) ? 'active' : 'inactive';

        // ── Streak ─────────────────────────────────────────────────────────────
        $streakDays = LearningStreak::where('learner_id', $user->id)
            ->max('current_streak_days') ?? 0;

        // ── Weekly study hours (Mon–Sun, current week) ─────────────────────────
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $weekSessions = LearnerLoginSession::where('user_id', $user->id)
            ->whereBetween('started_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->get(['started_at', 'duration_seconds']);

        $hoursByDay = array_fill_keys($days, 0.0);
        foreach ($weekSessions as $s) {
            $d = $s->started_at->format('D');
            if (isset($hoursByDay[$d])) {
                $hoursByDay[$d] += $s->duration_seconds / 3600;
            }
        }
        $weeklyStudyHours = array_map(
            fn ($day) => ['day' => $day, 'hours' => round($hoursByDay[$day], 1)],
            $days
        );

        // ── Recent activity (last 4 engagement events) ─────────────────────────
        $events = LearnerActivityEvent::where('user_id', $user->id)
            ->with('course:id,name,short_name')
            ->orderByDesc('occurred_at')
            ->take(4)
            ->get();

        $recentActivity = $events->map(fn ($e) => [
            'id'     => $e->id,
            'action' => ucfirst(str_replace('_', ' ', $e->event_type)),
            'item'   => $e->resource_type ?? '',
            'course' => $e->course?->short_name ?? $e->course?->name ?? '',
            'time'   => $e->occurred_at->diffForHumans(),
        ])->values();

        // ── Upcoming deadlines ─────────────────────────────────────────────────
        $rawDeadlines = Activity::whereIn('course_id', $courseIds)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'name', 'type', 'due_date', 'course_id']);

        $upcomingDeadlines = $rawDeadlines->map(fn ($a) => [
            'id'     => $a->id,
            'title'  => $a->name,
            'type'   => $a->type,
            'due'    => $a->due_date->format('M d, Y'),
            'urgent' => $a->due_date->diffInDays(now()) <= 2,
        ])->values();

        // ── Notifications ──────────────────────────────────────────────────────
        $notifications = NotificationModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // ── Latest AI nudge from instructor ────────────────────────────────────
        $latestNudge = NotificationModel::where('user_id', $user->id)
            ->where('type', 'engagement_nudge')
            ->orderBy('created_at', 'desc')
            ->first();

        // ── Degree programme ───────────────────────────────────────────────────
        $degreeProgramme = $user->degree_programme_id
            ? DegreeProgramme::with('college')->find($user->degree_programme_id)
            : null;

        // Enrolled courses array (many pages rely on this as an array of objects)
        $enrolledCoursesArr = $enrollments->map(fn ($e) => [
            'course'        => $e->course,
            'role'          => $e->role,
            'progress'      => $e->progress,
            'enrolled_date' => $e->enrolled_date,
        ]);

        return response()->json([
            'role'               => 'student',
            // Array form used by Lessons, Assignments, Quizzes, CourseProgress pages
            'enrolled_courses'   => $enrolledCoursesArr,
            // Numeric count used by Dashboard stats card
            'enrolled_count'     => $enrolledCount,
            'lessons_completed'  => $lessonsCompleted,
            'pending_tasks'      => $pendingTasks,
            'risk_signal'        => $riskSignal,
            'streak_days'        => $streakDays,
            'weekly_study_hours' => array_values($weeklyStudyHours),
            'recent_activity'    => $recentActivity,
            'upcoming_deadlines' => $upcomingDeadlines,
            'ai_nudge'           => $latestNudge ? [
                'id'         => $latestNudge->id,
                'title'      => $latestNudge->title,
                'body'       => $latestNudge->body,
                'course_id'  => $latestNudge->data['course_id'] ?? null,
                'sent_at'    => $latestNudge->created_at->diffForHumans(),
                'read'       => (bool) $latestNudge->read_at,
            ] : null,
            'monthly_progress'   => [],
            // Supporting data
            'stats' => [
                'enrolled_courses'  => $enrolledCount,
                'overall_progress'  => $enrollments->count() > 0 ? (int) round($enrollments->avg('progress')) : 0,
                'upcoming_activities' => $rawDeadlines->count(),
            ],
            'recent_notifications' => $notifications,
            'degree_programme'     => $degreeProgramme,
            'permissions' => [
                'can_manage_colleges'    => false,
                'can_manage_courses'     => false,
                'can_self_enroll'        => true,
                'can_view_own_data_only' => true,
            ],
        ]);
    }
}
