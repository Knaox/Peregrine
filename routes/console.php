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

// Final safety net for the per-user Pelican linking flow: dispatch a
// link job for any user that still has pelican_user_id=NULL. Covers
// edge cases where every retry of LinkPelicanAccountJob exhausted while
// Pelican was down — once Pelican comes back, the next 04:00 sweep
// catches up. The action is idempotent so re-dispatch is harmless.
Schedule::command('pelican:link-orphans')->dailyAt('04:00');

// Sweep theme upload slots and delete files no longer referenced by any
// setting. The Theme Studio upload endpoint intentionally keeps the prior
// file in place so an admin can revert during a single editing session;
// without this weekly cleanup, every login-background experiment would
// leave a permanent file behind.
Schedule::command('theme:cleanup-orphan-assets')->weeklyOn(0, '04:30');
