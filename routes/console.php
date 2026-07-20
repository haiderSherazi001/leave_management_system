<?php

use App\Console\Commands\SendMonthlyAttendanceReport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Timezone pinned explicitly: the scheduler otherwise evaluates against the
// server's system clock, not config('app.timezone') — "8:00 AM" must mean
// 8:00 AM Pakistan time regardless of what timezone the host runs on.
Schedule::command(SendMonthlyAttendanceReport::class)
    ->monthlyOn(1, '08:00')
    ->timezone(config('app.timezone'));
