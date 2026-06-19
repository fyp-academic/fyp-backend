<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Enrollment;
use App\Models\StudentGrade;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\Log;

/**
 * Evaluates a student's achievement metrics and awards any badges they now
 * qualify for. A student can hold several badges. Awards are idempotent.
 */
class BadgeService
{
    /**
     * Compute the metrics that badge criteria are measured against.
     * Keys must match badges.criteria_type.
     *
     * @return array<string,float>
     */
    public function metricsFor(User $user): array
    {
        $enrollments = Enrollment::where('user_id', $user->id)->get();

        $grades        = StudentGrade::where('student_id', $user->id)->get();
        $gradedCount   = $grades->where('status', 'graded')->count();
        $avgPercentage = $grades->where('status', 'graded')->avg('percentage') ?? 0;

        return [
            'enrolled_courses'    => (float) $enrollments->count(),
            'max_course_progress' => (float) ($enrollments->max('progress') ?? 0),
            'avg_grade'           => round((float) $avgPercentage, 1),
            'graded_items_count'  => (float) $gradedCount,
        ];
    }

    /**
     * Award all badges the user now qualifies for. Returns the freshly-awarded ones.
     *
     * @return array<int,Badge>
     */
    public function evaluate(User $user): array
    {
        try {
            $metrics  = $this->metricsFor($user);
            $badges   = Badge::all();
            $awarded  = [];

            foreach ($badges as $badge) {
                $metric = $metrics[$badge->criteria_type] ?? null;
                if ($metric === null || $metric < $badge->criteria_value) {
                    continue;
                }

                $userBadge = UserBadge::firstOrCreate(
                    ['user_id' => $user->id, 'badge_id' => $badge->id, 'course_id' => null],
                    ['earned_at' => now()],
                );

                if ($userBadge->wasRecentlyCreated) {
                    $awarded[] = $badge;
                }
            }

            return $awarded;
        } catch (\Throwable $e) {
            Log::warning('BadgeService: evaluate failed', ['user' => $user->id, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Earned badges shaped for the student profile `achievements` list
     * ({ icon, title, description, earned_at }).
     *
     * @return array<int,array<string,mixed>>
     */
    public function earnedFor(User $user): array
    {
        return UserBadge::with('badge')
            ->where('user_id', $user->id)
            ->orderByDesc('earned_at')
            ->get()
            ->filter(fn ($ub) => $ub->badge !== null)
            ->map(fn ($ub) => [
                'icon'        => $ub->badge->icon,
                'title'       => $ub->badge->name,
                'description' => $ub->badge->description,
                'tier'        => $ub->badge->tier,
                'earned_at'   => optional($ub->earned_at)->toDateString(),
            ])
            ->values()
            ->all();
    }
}
