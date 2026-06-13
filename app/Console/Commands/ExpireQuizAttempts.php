<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptResponse;
use App\Models\QuizQuestion;
use App\Traits\TimeEnforcementHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireQuizAttempts extends Command
{
    use TimeEnforcementHelper;

    protected $signature = 'quizzes:expire-attempts';

    protected $description = 'Auto-submit in-progress quiz attempts whose time limit or close window has passed';

    public function handle(): int
    {
        $now = now();
        $expired = 0;

        QuizAttempt::where('status', 'in_progress')
            ->whereNotNull('started_at')
            ->with('activity')
            ->chunkById(100, function ($attempts) use ($now, &$expired) {
                foreach ($attempts as $attempt) {
                    $activity = $attempt->activity ?? Activity::find($attempt->activity_id);
                    if (!$activity) {
                        continue;
                    }

                    $deadline = $this->quizAttemptDeadline(
                        $attempt->started_at,
                        $this->resolveQuizWindow($activity)
                    );

                    // No deadline, or not yet reached → leave it running.
                    if (!$deadline || $now->lessThanOrEqualTo($deadline)) {
                        continue;
                    }

                    // Grade whatever responses were already saved (usually none for
                    // a truly abandoned attempt); max score is the full paper.
                    $score = (float) QuizAttemptResponse::where('attempt_id', $attempt->id)->sum('marks_awarded');
                    $maxScore = (float) QuizQuestion::where('activity_id', $activity->id)->sum('default_mark');

                    $attempt->update([
                        'status'         => 'submitted',
                        'submitted_at'   => $deadline,
                        'time_spent'     => (int) $attempt->started_at->diffInSeconds($deadline),
                        'score'          => $score,
                        'max_score'      => $maxScore,
                        'auto_submitted' => true,
                    ]);

                    $expired++;
                }
            });

        if ($expired > 0) {
            Log::info("quizzes:expire-attempts auto-submitted {$expired} expired attempt(s)");
        }

        return Command::SUCCESS;
    }
}
