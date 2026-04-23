<?php

use App\Jobs\PurgeScheduledServerDeletionsJob;
use App\Jobs\SyncServerStatusJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SyncServerStatusJob)->everyFiveMinutes();

// Hard-delete servers whose Bridge grace period has expired (cancelled
// subscriptions where scheduled_deletion_at is in the past). Runs at
// 03:00 to avoid peak hours and to give "today" cancellations the
// configured grace window in full days.
Schedule::job(new PurgeScheduledServerDeletionsJob)->dailyAt('03:00');

// Prune the Stripe webhook idempotency ledger past the 30-day retention
// window. Runs at 03:30 (after the deletion purge so logs are easy to
// correlate). Stripe's max retry window is 3 days, so 30d is comfortable.
Schedule::command('stripe:clean-processed-events')->dailyAt('03:30');

// Prune the Pelican webhook idempotency ledger past the 2-day retention
// window. Runs at 03:45 (after the Stripe cleanup so logs correlate).
// Pelican does NOT retry, so a short retention is enough — keep 2d for
// debug/forensic value only.
Schedule::command('pelican:clean-processed-events')->dailyAt('03:45');
