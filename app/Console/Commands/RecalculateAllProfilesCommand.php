<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateProfileJob;
use App\Models\Enrollment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RecalculateAllProfilesCommand extends Command
{
    protected $signature   = 'profiles:recalculate-all {--chunk=50 : Batch size per dispatch wave}';
    protected $description = 'Dispatch RecalculateProfileJob for every student with an active enrollment.';

    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');

        $total = 0;

        Enrollment::where('status', 'active')
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id')
            ->chunk($chunkSize)
            ->each(function ($batch) use (&$total) {
                foreach ($batch as $studentId) {
                    // Spread dispatches by 2 s per student to avoid Gemini rate spikes
                    RecalculateProfileJob::dispatch((string) $studentId)
                        ->delay(now()->addSeconds($total * 2));
                    $total++;
                }
            });

        $this->info("Dispatched profile recalculation for {$total} students.");
        Log::info('profiles:recalculate-all dispatched', ['count' => $total]);

        return self::SUCCESS;
    }
}
