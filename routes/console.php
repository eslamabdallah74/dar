<?php

use App\Jobs\DataCleanupJob;
use App\Jobs\GenerateAnalyticsReportJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Background Queue Jobs
|--------------------------------------------------------------------------
|
| Scheduled commands and jobs are dispatched to their respective queues
| with overlap prevention, exponential backoff, and idempotent tracking.
|
*/

// 1. Direct Artisan scheduled tasks for automated billing & reminders
Schedule::command('dar:generate-rent-charges')
    ->dailyAt('00:05')
    ->name('dar-generate-rent-charges')
    ->withoutOverlapping();

Schedule::command('dar:send-rent-reminders')
    ->dailyAt('09:30')
    ->name('dar-send-rent-reminders')
    ->withoutOverlapping();

Schedule::command('dar:check-overdue')
    ->dailyAt('10:30')
    ->name('dar-check-overdue')
    ->withoutOverlapping();

Schedule::command('dar:check-expirations')
    ->dailyAt('11:30')
    ->name('dar-check-expirations')
    ->withoutOverlapping();

// 2. Generate daily occupancy and operational analytics report at 23:00 PM
Schedule::job(new GenerateAnalyticsReportJob('occupancy'))
    ->dailyAt('23:00')
    ->name('generate-occupancy-report')
    ->withoutOverlapping();

// 3. Run weekly housekeeping and log maintenance on Sundays at 03:00 AM
Schedule::job(new DataCleanupJob)
    ->weeklyOn(0, '03:00')
    ->name('weekly-data-cleanup')
    ->withoutOverlapping();
