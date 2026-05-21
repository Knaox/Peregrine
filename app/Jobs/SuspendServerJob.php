<?php

namespace App\Jobs;

use App\Events\Bridge\ServerSuspended;
use App\Events\Mirror\BroadcastsServerMirror;
use App\Models\Server;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Suspends a server in response to `customer.subscription.deleted`.
 *
 * Two-phase deletion :
 *   1. Immediate suspend : Pelican `suspendServer()` + local status='suspended'.
 *      Customer loses access right away (consistent with Stripe's view of
 *      the world : the subscription IS over).
 *   2. Schedule hard delete : set `Server.scheduled_deletion_at = now() + grace`.
 *      The daily `PurgeScheduledServerDeletionsJob` cron picks it up after
 *      the grace period and runs the actual delete. Admin can intervene
 *      during the grace period via "Cancel scheduled deletion" Filament
 *      action, which resets the column to null.
 *
 * Grace period source : `SettingsService::get('bridge_grace_period_days', 14)`.
 *   - 0 → schedule deletion at `now()` (purged at next cron run, ~24h max)
 *   - >0 → schedule deletion at `now()->addDays($N)`
 *
 * The `$scheduleDeletion = false` mode covers `subscription.updated → past_due`
 * where we suspend WITHOUT scheduling deletion (recoverable case).
 */
class SuspendServerJob implements ShouldQueue
{
    use BroadcastsServerMirror;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public function __construct(
        public readonly string $eventId,
        public readonly string $stripeSubscriptionId,
        public readonly bool $scheduleDeletion = false,
    ) {}

    public function handle(
        PelicanApplicationService $pelican,
        SettingsService $settings,
    ): void {
        $server = Server::where('stripe_subscription_id', $this->stripeSubscriptionId)->first();

        if ($server === null) {
            // Race: Stripe can deliver subscription.deleted before
            // checkout.session.completed has finished writing
            // stripe_subscription_id. Release back to the queue with the
            // configured backoff so the checkout handler has time to land.
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
                return;
            }
            Log::warning('SuspendServerJob: no Server matches stripe_subscription_id after retries', [
                'event_id' => $this->eventId,
                'stripe_subscription_id' => $this->stripeSubscriptionId,
                'attempts' => $this->attempts(),
            ]);
            return;
        }

        if ($server->pelican_server_id !== null && $server->status !== 'suspended') {
            try {
                $pelican->suspendServer($server->pelican_server_id);
            } catch (\Throwable $e) {
                Log::warning('SuspendServerJob: Pelican suspendServer failed, will retry', [
                    'event_id' => $this->eventId,
                    'server_id' => $server->id,
                    'message' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $updates = ['status' => 'suspended'];
        // We're suspending now, so any pending scheduled suspension is moot.
        if ($server->scheduled_suspension_at !== null) {
            $updates['scheduled_suspension_at'] = null;
        }
        // Only schedule deletion if not already planned: the two-phase
        // auto-renew flow (SubscriptionUpdateJob) may have set an exact
        // deletion date we must not overwrite with now()+grace.
        if ($this->scheduleDeletion && $server->scheduled_deletion_at === null) {
            $graceDays = (int) $settings->get('bridge_grace_period_days', 14);
            $updates['scheduled_deletion_at'] = now()->addDays(max(0, $graceDays));
        }
        $statusChanged = $server->status !== 'suspended';
        $server->update($updates);

        // Push the new status out to every subscriber (dashboard list,
        // server detail page, admin mirror) so the suspended pill / page
        // gates flip in real time. The trait swallows broadcast errors
        // so a Reverb outage cannot regress the suspension itself.
        if ($statusChanged) {
            $this->broadcastServerMirrorChanged($server);
        }

        Log::info('SuspendServerJob: server suspended', [
            'event_id' => $this->eventId,
            'server_id' => $server->id,
            'scheduled_deletion_at' => optional($server->scheduled_deletion_at)->toIso8601String(),
        ]);

        // Customer-facing notification only when this is a "subscription
        // cancelled" suspend (scheduleDeletion=true) — past_due transient
        // suspends are silent (Stripe is already retrying the card and
        // will email the customer about the failed payment itself).
        if ($this->scheduleDeletion && $server->user) {
            event(new ServerSuspended($server->fresh(), $server->user));
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SuspendServerJob: terminal failure', [
            'event_id' => $this->eventId,
            'stripe_subscription_id' => $this->stripeSubscriptionId,
            'message' => $e->getMessage(),
        ]);
    }
}
