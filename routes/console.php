<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Notification digest scheduler - runs every hour
Schedule::command('notifications:send-digests')->hourly();

// Auto-start and auto-end sessions based on scheduled time + duration
Schedule::command('sessions:update-statuses')->everyMinute();

// Weekly full profile refresh — catches passive learners who never trigger events
Schedule::command('profiles:recalculate-all')->weekly()->sundays()->at('02:00');
