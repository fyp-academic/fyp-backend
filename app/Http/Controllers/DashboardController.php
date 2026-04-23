<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Activity;
use App\Models\StudentGrade;
use App\Models\Notification as NotificationModel;
use App\Models\DashboardEngagement;

class DashboardController extends Controller
{
    /**
     * GET /api/v1/dashboard/admin
     * Platform-wide statistics for admin users.
     */
    public function admin(Request $request): JsonResponse
    {
        return response()->json([
            'total_users'       => User::count(),
            'total_courses'     => Course::count(),
            'total_enrollments' => Enrollment::count(),
            'active_courses'    => Course::where('status', 'active')->count(),
            'system_health'     => 'ok',
        ]);
    }

    /**
     * GET /api/v1/dashboard/instructor
     * Active courses, enrollments, engagement, and pending grading for the instructor.
     */
    public function instructor(Request $request): JsonResponse
    {
        $user = $request->user();

        $courses = Course::where('instructor_id', $user->id)
            ->with('sections.activities')
            ->get();

        $courseIds = $courses->pluck('id');

        $totalEnrollments = Enrollment::whereIn('course_id', $courseIds)
            ->where('role', 'student')->count();

        $engagement = DashboardEngagement::whereIn('course_id', $courseIds)
            ->orderBy('week_of', 'desc')
            ->limit(7)
            ->get()
            ->map(fn ($e) => [
                'day'         => $e->day_label,
                'active'      => $e->active_students,
                'submissions' => $e->submissions,
            ]);

        $pendingGrades = StudentGrade::whereHas('gradeItem', function ($q) use ($courseIds) {
                $q->whereIn('course_id', $courseIds);
            })
            ->whereIn('status', ['submitted', 'not_submitted'])
            ->count();

        $notifications = NotificationModel::where('user_id', $user->id)
            ->orderByRaw("CASE WHEN read = false THEN 0 ELSE 1 END")
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $activeCount = $courses->where('status', 'active')->count();

        return response()->json([
            'active_courses'        => $activeCount,
            'total_enrollments'     => $totalEnrollments,
            'weekly_engagement'     => $engagement,
            'pending_grading_count' => $pendingGrades,
            'recent_notifications'  => $notifications,
        ]);
    }

    /**
     * GET /api/v1/dashboard/student
     * Enrolled courses, progress, upcoming activities, and notifications for the student.
     */
    public function student(Request $request): JsonResponse
    {
        $user = $request->user();

        $enrollments = Enrollment::where('user_id', $user->id)
            ->with('course.sections.activities')
            ->get();

        $enrolledCourses = $enrollments->map(fn ($e) => [
            'course'   => $e->course,
            'role'     => $e->role,
            'progress' => $e->progress,
        ]);

        $overallProgress = $enrollments->count() > 0
            ? round($enrollments->avg('progress'), 1)
            : 0;

        $courseIds     = $enrollments->pluck('course_id');
        $upcomingDues  = Activity::whereIn('course_id', $courseIds)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->orderBy('due_date')
            ->limit(10)
            ->get(['id', 'name', 'type', 'due_date', 'course_id']);

        $notifications = NotificationModel::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'enrolled_courses'     => $enrolledCourses,
            'overall_progress'     => $overallProgress,
            'upcoming_due_dates'   => $upcomingDues,
            'recent_notifications' => $notifications,
        ]);
    }
}
