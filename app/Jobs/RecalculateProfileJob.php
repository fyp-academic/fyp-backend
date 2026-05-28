<?php

namespace App\Jobs;

use App\Services\StudentProfileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RecalculateProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private string $studentId) {}

    public function handle(StudentProfileService $profileService): void
    {
        try {
            $profile = $profileService->recalculate($this->studentId);

            // Delete Redis cache keys for this student so the new profile hash is used
            $pattern = 'adapt:' . $this->studentId . ':*';
            $this->deleteRedisPattern($pattern);

            Log::info('Student profile recalculated', [
                'student_id' => $this->studentId,
                'profile_hash' => $profile['profile_hash'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Profile recalculation failed', [
                'student_id' => $this->studentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RecalculateProfileJob permanently failed', [
            'student_id' => $this->studentId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function deleteRedisPattern(string $pattern): void
    {
        try {
            $redis = Redis::connection();
            $keys = $redis->keys($pattern);
            if (! empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not delete Redis keys during profile recalculation', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
