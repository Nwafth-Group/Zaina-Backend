<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:send-reminders')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('bookings:auto-update-status')->everyThirtyMinutes()->withoutOverlapping();
Schedule::command('bookings:check-at-risk')->everyTenMinutes()->withoutOverlapping();
Schedule::command('points:cleanup-expired-referrals')->daily()->withoutOverlapping();

