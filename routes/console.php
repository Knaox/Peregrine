<?php

use App\Jobs\Bridge\ReconcilePelicanMirrorsJob;
use App\Jobs\PurgeScheduledServerDeletionsJob;
use App\Jobs\SyncServerStatusJob;
use App\Services\SettingsService;
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

// Pelican mirror reconciliation. Two cadences: hourly safety-net when the
// webhook receiver is enabled (only Backup + Allocation — the ressources
// hit by Pelican's mass-update bypasses), or daily full-sync when the
// receiver is OFF (covers admins who didn't configure the webhook). The
// closure reads the setting at fire time so toggling pelican_webhook_enabled
// flips behaviour at the next tick without a restart.
Schedule::call(function () {
    $enabled = (string) app(SettingsService::class)->get('pelican_webhook_enabled', 'false');
    $scope = ($enabled === 'true' || $enabled === '1')
        ? ReconcilePelicanMirrorsJob::SCOPE_SAFETY_NET
        : ReconcilePelicanMirrorsJob::SCOPE_FULL_SYNC;
    ReconcilePelicanMirrorsJob::dispatch($scope);
})->name('pelican:reconcile-mirrors')->hourly()->withoutOverlapping();
