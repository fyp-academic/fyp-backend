<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\QuizAttempt;
use App\Models\StudentProfile;
use App\Models\UserActivityCompletion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentProfileService
{
    /**
     * Recalculate a student's learning profile from existing LMS data.
     */
    public function recalculate(string $studentId): array
    {
        $quizAverage = $this->calculateQuizAverage($studentId);
        $pace = $this->calculatePace($studentId);
        $weakTopics = $this->calculateWeakTopics($studentId);
        $preferredModality = $this->calculatePreferredModality($studentId);
        $completionRate = $this->calculateCompletionRate($studentId);

        $profileData = [
            'pace' => $pace,
            'quiz_average' => round($quizAverage, 2),
            'weak_topics' => $weakTopics,
            'preferred_modality' => $preferredModality,
            'completion_rate' => round($completionRate, 2),
        ];

        ksort($profileData);
        $profileHash = md5(json_encode($profileData));

        $profile = StudentProfile::updateOrCreate(
            ['student_id' => $studentId],
            array_merge($profileData, [
                'id' => Str::uuid()->toString(),
                'profile_hash' => $profileHash,
                'updated_at' => now(),
            ])
        );

        return array_merge($profileData, ['profile_hash' => $profileHash]);
    }

    private function calculateQuizAverage(string $studentId): float
    {
        $avg = QuizAttempt::where('student_id', $studentId)
            ->whereNotNull('score')
            ->whereNotNull('max_score')
            ->where('max_score', '>', 0)
            ->selectRaw('AVG((score / max_score) * 100) as avg_score')
            ->value('avg_score');

        return (float) ($avg ?? 0);
    }

    private function calculatePace(string $studentId): string
    {
        // Compare student's avg time-per-topic against course baseline.
        // If > 1.5x baseline -> slow. If < 0.7x -> fast. Otherwise medium.
        $studentAvg = DB::table('quiz_attempts')
            ->where('student_id', $studentId)
            ->whereNotNull('time_spent')
            ->avg('time_spent');

        if ($studentAvg === null) {
            return 'medium';
        }

        $globalAvg = DB::table('quiz_attempts')
            ->whereNotNull('time_spent')
            ->avg('time_spent');

        if ($globalAvg === null || $globalAvg == 0) {
            return 'medium';
        }

        $ratio = $studentAvg / $globalAvg;

        if ($ratio > 1.5) {
            return 'slow';
        }
        if ($ratio < 0.7) {
            return 'fast';
        }

        return 'medium';
    }

    private function calculateWeakTopics(string $studentId): array
    {
        // Topics where quiz score < 60% OR retry_count > 2
        // We approximate "topic" by activity/section since there is no explicit topics table.
        $weak = [];

        $lowScoreActivities = DB::table('quiz_attempts')
            ->join('activities', 'quiz_attempts.activity_id', '=', 'activities.id')
            ->join('sections', 'activities.section_id', '=', 'sections.id')
            ->where('quiz_attempts.student_id', $studentId)
            ->whereNotNull('quiz_attempts.score')
            ->whereNotNull('quiz_attempts.max_score')
            ->whereRaw('(quiz_attempts.score / quiz_attempts.max_score) * 100 < 60')
            ->select('sections.title as topic_name')
            ->distinct()
            ->pluck('topic_name')
            ->toArray();

        $highRetryActivities = DB::table('quiz_attempts')
            ->join('activities', 'quiz_attempts.activity_id', '=', 'activities.id')
            ->join('sections', 'activities.section_id', '=', 'sections.id')
            ->where('quiz_attempts.student_id', $studentId)
            ->select('activities.id as activity_id', 'sections.title as topic_name', DB::raw('COUNT(*) as attempts'))
            ->groupBy('activities.id', 'sections.title')
            ->havingRaw('COUNT(*) > 2')
            ->pluck('topic_name')
            ->toArray();

        $weak = array_unique(array_merge($lowScoreActivities, $highRetryActivities));
        sort($weak);

        return array_values($weak);
    }

    private function calculatePreferredModality(string $studentId): string
    {
        $scores = DB::table('material_interactions')
            ->join('course_materials', 'material_interactions.material_id', '=', 'course_materials.id')
            ->where('material_interactions.student_id', $studentId)
            ->select(
                'course_materials.type',
                DB::raw('SUM(material_interactions.total_duration_seconds) as total_time'),
                DB::raw('AVG(material_interactions.video_watch_percent) as avg_video'),
                DB::raw('AVG(material_interactions.pdf_scroll_depth_percent) as avg_pdf'),
            )
            ->groupBy('course_materials.type')
            ->orderByDesc('total_time')
            ->get();

        if ($scores->isNotEmpty()) {
            $topType = $scores->first()->type;

            return match ($topType) {
                'video', 'youtube' => 'visual',
                'h5p', 'scorm' => 'example-based',
                'image' => 'visual',
                default => 'text',
            };
        }

        $user = DB::table('users')->where('id', $studentId)->first();
        if ($user) {
            $modes = json_decode($user->preferred_modes ?? '[]', true) ?: [];
            if (in_array('video', $modes, true) || in_array('multimedia', $modes, true)) {
                return 'visual';
            }
            if (in_array('classroom', $modes, true) || in_array('live', $modes, true)) {
                return 'example-based';
            }

            $vark = $user->vark_style ?? null;
            if ($vark === 'visual') {
                return 'visual';
            }
            if ($vark === 'kinesthetic') {
                return 'example-based';
            }
        }

        return 'text';
    }

    private function calculateCompletionRate(string $studentId): float
    {
        $enrolled = Enrollment::where('user_id', $studentId)
            ->where('status', 'active')
            ->count();

        if ($enrolled === 0) {
            return 0;
        }

        // Each row is a completion (no boolean `completed` column exists).
        $completed = UserActivityCompletion::where('user_id', $studentId)
            ->distinct('activity_id')
            ->count('activity_id');

        // Approximate total activities across enrolled courses
        $totalActivities = DB::table('enrollments')
            ->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->join('sections', 'courses.id', '=', 'sections.course_id')
            ->join('activities', 'sections.id', '=', 'activities.section_id')
            ->where('enrollments.user_id', $studentId)
            ->where('enrollments.status', 'active')
            ->count('activities.id');

        if ($totalActivities === 0) {
            return 0;
        }

        return min(100, round(($completed / $totalActivities) * 100, 2));
    }
}
