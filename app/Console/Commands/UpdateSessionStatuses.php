<?php

namespace App\Console\Commands;

use App\Services\Jitsi\SessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateSessionStatuses extends Command
{
    protected $signature = 'sessions:update-statuses';

    protected $description = 'Auto-start and auto-end video sessions based on scheduled time and duration';

    public function handle(SessionService $sessionService): int
    {
        try {
            $sessionService->autoUpdateStatuses();
            Log::debug('sessions:update-statuses ran successfully');
        } catch (\Throwable $e) {
            Log::error('sessions:update-statuses failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
