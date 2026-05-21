<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\Mirror\BroadcastsServerMirror;
use App\Models\Server;
use App\Models\ServerConfiguration;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Handles `customer.subscription.updated` events from Stripe.
 *
 * Two reasons we care :
 *   1. Configuration change (upgrade/downgrade) : the
 *      `metadata.peregrine_configuration_id` on the subscription has changed
 *      → look up the new ServerConfiguration and push the new resource
 *      limits to Pelican via `updateServerBuild()`. Update local
 *      Server.server_configuration_id.
 *   2. Status change to `past_due` : Stripe's dunning is failing. Soft-suspend
 *      the server so the customer loses access until they update payment.
 *      No scheduled deletion (this is recoverable). When Stripe gives up
 *      and emits `subscription.deleted`, the SuspendServerJob handles the
 *      hard suspend + scheduled deletion path.
 *
 * Configuration resolution is metadata-driven : the inbound shop tags every
 * Stripe Subscription with `metadata.peregrine_configuration_id`. Stripe
 * never invented this id — Peregrine owns it. The legacy `stripe_price_id`
 * lookup was removed in Phase 1.
 *
 * Idempotent : same event re-delivered → same outcome (Pelican
 * updateServerBuild is itself idempotent for identical limits ; status
 * field is set to the target value regardless of current state).
 */
class SubscriptionUpdateJob implements ShouldQueue
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
        public readonly ?int $newConfigurationId,
        public readonly string $newStatus,
        public readonly bool $cancelAtPeriodEnd = false,
        public readonly ?int $cancelAt = null,
    ) {}

    public function handle(PelicanApplicationService $pelican): void
    {
        $server = Server::where('stripe_subscription_id', $this->stripeSubscriptionId)->first();

        if ($server === null) {
            // Race: Stripe can deliver subscription.updated before
            // checkout.session.completed has finished writing
            // stripe_subscription_id on the local Server row. Release the job
            // back to the queue so the configured backoff (60s, 300s, 900s)
            // gives the checkout handler time to land. After tries are
            // exhausted, fall through and accept the loss with a warning.
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[$this->attempts() - 1] ?? 60);
                return;
            }
            Log::warning('SubscriptionUpdateJob: no Server matches stripe_subscription_id after retries', [
                'event_id' => $this->eventId,
                'stripe_subscription_id' => $this->stripeSubscriptionId,
                'attempts' => $this->attempts(),
            ]);
            return;
        }

        // 1. Configuration change (metadata.peregrine_configuration_id differs
        // from the server's current server_configuration_id).
        if ($this->newConfigurationId !== null
            && $this->newConfigurationId !== $server->server_configuration_id
        ) {
            $newConfiguration = ServerConfiguration::find($this->newConfigurationId);
            if ($newConfiguration === null) {
                Log::warning('SubscriptionUpdateJob: unknown peregrine_configuration_id', [
                    'event_id' => $this->eventId,
                    'new_configuration_id' => $this->newConfigurationId,
                    'server_id' => $server->id,
                ]);
                // Fall through to status handling — don't bail (status change
                // might still be relevant).
            } elseif ($server->pelican_server_id !== null) {
                try {
                    $pelican->updateServerBuild($server->pelican_server_id, [
                        'memory' => (int) ($newConfiguration->ram ?? 0),
                        'swap' => (int) ($newConfiguration->swap_mb ?? 0),
                        'disk' => (int) ($newConfiguration->disk ?? 0),
                        'io' => (int) ($newConfiguration->io_weight ?? 500),
                        'cpu' => (int) ($newConfiguration->cpu ?? 0),
                        'oom_disabled' => ! (bool) $newConfiguration->enable_oom_killer,
                        'feature_limits' => [
                            'databases' => (int) ($newConfiguration->feature_limits_databases ?? 0),
                            'backups' => (int) ($newConfiguration->feature_limits_backups ?? 3),
                            'allocations' => (int) ($newConfiguration->feature_limits_allocations ?? 1),
                        ],
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('SubscriptionUpdateJob: Pelican updateServerBuild failed, will retry', [
                        'event_id' => $this->eventId,
                        'server_id' => $server->id,
                        'message' => $e->getMessage(),
                    ]);
                    throw $e; // queue retry
                }

                $oldConfigurationId = $server->server_configuration_id;
                $server->update(['server_configuration_id' => $newConfiguration->id]);
                Log::info('SubscriptionUpdateJob: configuration upgraded/downgraded', [
                    'server_id' => $server->id,
                    'old_configuration_id' => $oldConfigurationId,
                    'new_configuration_id' => $newConfiguration->id,
                ]);
            }
        }

        // 2. Status changes (past_due = soft suspend, no scheduled deletion).
        // active/trialing → unsuspend if currently suspended.
        if ($this->newStatus === 'past_due' && $server->status !== 'suspended') {
            if ($server->pelican_server_id !== null) {
                try {
                    $pelican->suspendServer($server->pelican_server_id);
                } catch (\Throwable $e) {
                    Log::warning('SubscriptionUpdateJob: Pelican suspendServer failed, will retry', [
                        'server_id' => $server->id,
                        'message' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
            $server->update(['status' => 'suspended']);
            // Broadcast so the React shell flips the dashboard pill +
            // sidebar gates the moment Stripe drops us into past_due,
            // not on the next 5-minute staleTime refetch.
            $this->broadcastServerMirrorChanged($server);
        } elseif (in_array($this->newStatus, ['active', 'trialing'], true)
            && $server->status === 'suspended'
            && $server->scheduled_deletion_at === null
        ) {
            // Customer fixed their payment, reactivate. The scheduled_deletion_at
            // guard is essential and applies to EVERY subscription (standard or
            // resubscribed): Stripe does not guarantee event ordering and
            // re-delivers events for up to 3 days, so a stale "active" update
            // emitted at subscribe time can land AFTER the cancellation that
            // suspended + scheduled the server for deletion. We only auto-revive
            // servers suspended for non-payment (past_due leaves
            // scheduled_deletion_at null). A terminally cancelled server is
            // revived solely by an explicit paid resubscribe (which clears
            // scheduled_deletion_at), never by a replayed/out-of-order event.
            if ($server->pelican_server_id !== null) {
                try {
                    $pelican->unsuspendServer($server->pelican_server_id);
                } catch (\Throwable $e) {
                    Log::warning('SubscriptionUpdateJob: Pelican unsuspendServer failed, will retry', [
                        'server_id' => $server->id,
                        'message' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
            $server->update(['status' => 'active']);
            // Broadcast the unsuspend so the user's panel un-greys
            // (sidebar entries return, dashboard pill drops) without
            // requiring a refresh after they pay.
            $this->broadcastServerMirrorChanged($server);
        }

        // 3. Auto-renew toggle on an ACTIVE server. Disabling renewal does not
        // suspend — the customer keeps the server until the paid period ends —
        // but we mark the upcoming removal so the dashboard/admin show
        // "deletion scheduled for <period end>". At period end Stripe emits
        // subscription.deleted and SuspendServerJob takes over (suspend +
        // purge after grace), so this is purely the scheduled-state signal.
        //
        // Invariant making the "clear" branch safe: an ACTIVE server only ever
        // carries scheduled_deletion_at because auto-renew was disabled here
        // (every suspend path sets status='suspended'). So re-enabling renewal
        // can clear it without ever reviving a terminally-cancelled server.
        if (in_array($this->newStatus, ['active', 'trialing'], true) && $server->status === 'active') {
            if ($this->cancelAtPeriodEnd && $this->cancelAt !== null) {
                if ($server->scheduled_deletion_at?->timestamp !== $this->cancelAt) {
                    $target = \Carbon\CarbonImmutable::createFromTimestamp($this->cancelAt);
                    $server->update(['scheduled_deletion_at' => $target]);
                    $this->broadcastServerMirrorChanged($server);
                    Log::info('SubscriptionUpdateJob: auto-renew disabled → deletion scheduled', [
                        'server_id' => $server->id,
                        'scheduled_deletion_at' => $target->toIso8601String(),
                    ]);
                }
            } elseif (! $this->cancelAtPeriodEnd && $server->scheduled_deletion_at !== null) {
                $server->update(['scheduled_deletion_at' => null]);
                $this->broadcastServerMirrorChanged($server);
                Log::info('SubscriptionUpdateJob: auto-renew re-enabled → scheduled deletion cleared', [
                    'server_id' => $server->id,
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SubscriptionUpdateJob: terminal failure', [
            'event_id' => $this->eventId,
            'stripe_subscription_id' => $this->stripeSubscriptionId,
            'message' => $e->getMessage(),
        ]);
    }
}
