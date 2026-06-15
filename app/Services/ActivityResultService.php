<?php

namespace App\Services;

use App\Jobs\RecalculateProfileJob;
use App\Models\Activity;
use App\Models\Enrollment;
use App\Models\GradeItem;
use App\Models\StudentGrade;
use App\Models\User;
use App\Models\UserActivityCompletion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Shared completion + gradebook plumbing for self-reporting activities
 * (SCORM commits, H5P xAPI results). Mirrors ActivityController@complete so
 * progress, learner-profile recalculation and engagement stay consistent.
 */
class ActivityResultService
{
    /**
     * Mark an activity complete for a student, refresh course progress and
     * trigger profile recalculation. Returns the new course progress (0-100).
     */
    public function recordCompletion(Activity $activity, string $userId, string $completionType = 'completed'): float
    {
        UserActivityCompletion::updateOrCreate(
            ['user_id' => $userId, 'activity_id' => $activity->id],
            [
                'id'              => (string) Str::uuid(),
                'course_id'       => $activity->course_id,
                'completion_type' => $completionType,
                'completed_at'    => now(),
            ]
        );

        $progress = UserActivityCompletion::progressFor($userId, $activity->course_id);

        $enrollment = Enrollment::where('user_id', $userId)
            ->where('course_id', $activity->course_id)
            ->first();

        if ($enrollment) {
            $enrollment->progress    = $progress;
            $enrollment->last_access = now();
            $enrollment->save();
        }

        RecalculateProfileJob::dispatch($userId)->delay(now()->addSeconds(3));

        try {
            app(EngagementComputationService::class)->logEvent(
                userId:       $userId,
                eventType:    'activity_complete',
                courseId:     $activity->course_id,
                resourceType: 'activity',
                resourceId:   $activity->id,
                value:        $progress,
                metadata:     ['activity_type' => $activity->type],
            );
        } catch (\Throwable $e) {
            Log::warning('ActivityResultService: engagement log failed', [
                'activity' => $activity->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return $progress;
    }

    /**
     * Write a numeric score into the gradebook for an activity, creating the
     * GradeItem on demand. The package's own max ($scoreMax) is scaled onto the
     * activity's configured grade_max so the gradebook stays consistent.
     */
    public function recordScore(Activity $activity, string $userId, ?float $scoreRaw, ?float $scoreMax = null): ?StudentGrade
    {
        if ($scoreRaw === null) {
            return null;
        }

        $gradeMax = (float) ($activity->grade_max ?? 0);
        if ($gradeMax <= 0) {
            $gradeMax = ($scoreMax && $scoreMax > 0) ? $scoreMax : 100;
        }

        $gradeItem = GradeItem::firstOrNew([
            'course_id'   => $activity->course_id,
            'activity_id' => $activity->id,
        ]);
        if (! $gradeItem->exists) {
            $gradeItem->id = (string) Str::uuid();
        }
        $gradeItem->fill([
            'course_id'     => $activity->course_id,
            'activity_id'   => $activity->id,
            'activity_name' => $activity->name,
            'activity_type' => $activity->type,
            'grade_max'     => $gradeMax,
        ])->save();

        // Scale the package's raw score onto the gradebook max when the package
        // reports a different maximum (e.g. SCORM score_max=10, gradebook=100).
        $grade = $scoreRaw;
        if ($scoreMax && $scoreMax > 0 && abs($scoreMax - $gradeMax) > 0.001) {
            $grade = round(($scoreRaw / $scoreMax) * $gradeMax, 2);
        }
        $grade      = max(0, min($grade, $gradeMax));
        $percentage = $gradeMax > 0 ? round(($grade / $gradeMax) * 100, 1) : 0;

        $student = User::find($userId);

        $sg = StudentGrade::firstOrNew([
            'grade_item_id' => $gradeItem->id,
            'student_id'    => $userId,
        ]);
        if (! $sg->exists) {
            $sg->id = (string) Str::uuid();
        }
        $sg->fill([
            'student_name'   => $student?->name ?? '',
            'grade'          => $grade,
            'percentage'     => $percentage,
            'submitted_date' => now()->toDateString(),
            'status'         => 'graded',
        ])->save();

        return $sg;
    }
}
