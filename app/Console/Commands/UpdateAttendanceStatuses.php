<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Traits\TimeEnforcementHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateAttendanceStatuses extends Command
{
    use TimeEnforcementHelper;

    protected $signature = 'attendance:update-statuses';

    protected $description = 'Auto open/close attendance sessions based on session_date + duration';

    public function handle(): int
    {
        $updated = 0;

        AttendanceSession::whereIn('status', ['scheduled', 'open'])
            ->whereNotNull('session_date')
            ->chunkById(200, function ($sessions) use (&$updated) {
                foreach ($sessions as $session) {
                    $status = $this->getSessionTimeStatus($session->session_date, $session->duration_minutes)['status'];
                    $target = $status === 'active' ? 'open' : $status; // scheduled | open | closed

                    if ($target !== $session->status) {
                        $session->update(['status' => $target]);
                        $updated++;
                    }
                }
            });

        if ($updated > 0) {
            Log::info("attendance:update-statuses updated {$updated} session(s)");
        }

        return Command::SUCCESS;
    }
}
