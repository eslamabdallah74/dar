<?php

use App\Jobs\CheckOverdueRentChargesJob;
use App\Jobs\DataCleanupJob;
use App\Jobs\GenerateAnalyticsReportJob;
use App\Jobs\GenerateRentChargesJob;
use App\Jobs\LeaseExpiryReminderJob;
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

// 1. Generate monthly rent charges for active leases at 01:00 AM
Schedule::job(new GenerateRentChargesJob)
    ->dailyAt('01:00')
    ->name('generate-monthly-rent-charges')
    ->withoutOverlapping();

// 2. Identify overdue unpaid rent charges and dispatch reminders at 09:00 AM
Schedule::job(new CheckOverdueRentChargesJob)
    ->dailyAt('09:00')
    ->name('check-overdue-rent-charges')
    ->withoutOverlapping();

// 3. Scan for upcoming lease expiries (30, 15, 7 days) at 10:00 AM
Schedule::job(new LeaseExpiryReminderJob)
    ->dailyAt('10:00')
    ->name('check-lease-expiry-reminders')
    ->withoutOverlapping();

// 4. Generate daily occupancy and operational analytics report at 23:00 PM
Schedule::job(new GenerateAnalyticsReportJob('occupancy'))
    ->dailyAt('23:00')
    ->name('generate-occupancy-report')
    ->withoutOverlapping();

// 5. Run weekly housekeeping and log maintenance on Sundays at 03:00 AM
Schedule::job(new DataCleanupJob)
    ->weeklyOn(0, '03:00')
    ->name('weekly-data-cleanup')
    ->withoutOverlapping();

// 6. Direct Artisan scheduled tasks for automated billing & reminders
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
