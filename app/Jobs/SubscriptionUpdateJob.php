<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\ServerPlan;
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
 *   1. Plan change (upgrade/downgrade) : the price_id has changed → look up
 *      the new ServerPlan and push the new resource limits to Pelican via
 *      `updateServerBuild()`. Update local Server.plan_id.
 *   2. Status change to `past_due` : Stripe's dunning is failing. Soft-suspend
 *      the server so the customer loses access until they update payment.
 *      No scheduled deletion (this is recoverable). When Stripe gives up
 *      and emits `subscription.deleted`, the SuspendServerJob handles the
 *      hard suspend + scheduled deletion path.
 *
 * Idempotent : same event re-delivered → same outcome (Pelican
 * updateServerBuild is itself idempotent for identical limits ; status
 * field is set to the target value regardless of current state).
 */
class SubscriptionUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public function __construct(
        public readonly string $eventId,
        public readonly string $stripeSubscriptionId,
        public readonly ?string $newStripePriceId,
        public readonly string $newStatus,
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

        // 1. Plan change (price_id different from current plan's stripe_price_id)
        if ($this->newStripePriceId !== null
            && $server->plan
            && $server->plan->stripe_price_id !== $this->newStripePriceId
        ) {
            $newPlan = ServerPlan::where('stripe_price_id', $this->newStripePriceId)->first();
            if ($newPlan === null) {
                Log::warning('SubscriptionUpdateJob: new stripe_price_id has no matching ServerPlan', [
                    'event_id' => $this->eventId,
                    'new_price_id' => $this->newStripePriceId,
                    'server_id' => $server->id,
                ]);
                // Fall through to status handling — don't bail (status change might still be relevant).
            } elseif ($server->pelican_server_id !== null) {
                try {
                    $pelican->updateServerBuild($server->pelican_server_id, [
                        'memory' => (int) ($newPlan->ram ?? 0),
                        'swap' => (int) ($newPlan->swap_mb ?? 0),
                        'disk' => (int) ($newPlan->disk ?? 0),
                        'io' => (int) ($newPlan->io_weight ?? 500),
                        'cpu' => (int) ($newPlan->cpu ?? 0),
                        'oom_disabled' => ! (bool) $newPlan->enable_oom_killer,
                        'feature_limits' => [
                            'databases' => (int) ($newPlan->feature_limits_databases ?? 0),
                            'backups' => (int) ($newPlan->feature_limits_backups ?? 3),
                            'allocations' => (int) ($newPlan->feature_limits_allocations ?? 1),
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

                $server->update(['plan_id' => $newPlan->id]);
                Log::info('SubscriptionUpdateJob: plan upgraded/downgraded', [
                    'server_id' => $server->id,
                    'old_plan_id' => $server->plan_id,
                    'new_plan_id' => $newPlan->id,
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
        } elseif (in_array($this->newStatus, ['active', 'trialing'], true) && $server->status === 'suspended') {
            // Customer fixed their payment, reactivate.
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
