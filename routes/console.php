<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

// Leakwatch schedules (run `php artisan schedule:work` locally, or a cron in production).
Schedule::command('leakwatch:scan-due')->hourly()->withoutOverlapping();
Schedule::command('leakwatch:check-certificates')->dailyAt('06:10');
Schedule::command('leakwatch:refresh-advisories')->dailyAt('06:40');
Schedule::command('leakwatch:reverify-sites')->weeklyOn(1, '05:30');
