<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Services\EngagementComputationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecomputeEngagement extends Command
{
    protected $signature = 'engagement:recompute
        {--course= : Limit recomputation to a single course id}
        {--week= : Force a specific week number (defaults to each course\'s current week)}';

    protected $description = 'Recompute weekly engagement scores for active enrollments from measured telemetry.';

    public function handle(EngagementComputationService $service): int
    {
        // First, defensively close abandoned login sessions so time-on-task and
        // bounce stats are accurate before we score.
        $closed = $service->closeStaleSessions();
        if ($closed > 0) {
            $this->info("Closed {$closed} stale login session(s).");
        }

        $query = Enrollment::where('status', 'active');
        if ($courseFilter = $this->option('course')) {
            $query->where('course_id', $courseFilter);
        }

        $enrollments = $query->get(['user_id', 'course_id']);

        // Cache the resolved week per course so we don't recompute it per learner.
        $weekByCourse = [];
        $forcedWeek   = $this->option('week') !== null ? (int) $this->option('week') : null;

        $computed = 0;
        $failed   = 0;

        foreach ($enrollments as $enrollment) {
            $courseId = $enrollment->course_id;
            $week = $forcedWeek
                ?? ($weekByCourse[$courseId] ??= $service->currentWeek($courseId));

            try {
                $service->computeForWeek((string) $enrollment->user_id, (string) $courseId, $week);
                $computed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('engagement:recompute failed', [
                    'user_id'   => $enrollment->user_id,
                    'course_id' => $courseId,
                    'week'      => $week,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $this->info("Recomputed engagement for {$computed} enrollment(s); {$failed} failed.");
        Log::info('engagement:recompute complete', ['computed' => $computed, 'failed' => $failed]);

        return self::SUCCESS;
    }
}
