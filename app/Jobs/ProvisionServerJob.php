<?php

namespace App\Jobs;

use App\Actions\Pelican\EnsurePelicanAccountAction;
use App\Events\Bridge\ServerProvisioned;
use App\Jobs\Bridge\MonitorServerInstallationJob;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerPlan;
use App\Models\User;
use App\Services\Bridge\EnvironmentResolver;
use App\Services\Bridge\PortAllocator;
use App\Services\Pelican\DTOs\CreateServerRequest;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates the full provisioning of a Pelican server from a ServerPlan.
 * Used by both the "Create test server" button (debug) and (later) the
 * Stripe webhook handler (P3). Idempotent and crash-safe :
 *
 *   1. Create local Server row UPFRONT with status='provisioning' and
 *      idempotency_key = unique-per-attempt. The row is visible in
 *      /admin/servers from second one.
 *   2. Look up egg variable defaults from Pelican (mandatory or Pelican
 *      rejects).
 *   3. Allocate consecutive ports.
 *   4. Resolve env vars (egg defaults overridden by plan env_var_mapping).
 *   5. Call createServerAdvanced.
 *   6. On success : update local row with pelican_server_id + status='active'.
 *   7. On any failure : update local row with status='provisioning_failed'
 *      + provisioning_error, then re-throw so Laravel queue retries.
 *
 * Retry policy : 3 tries, exponential backoff [60s, 300s, 900s]. After
 * exhaustion, the row stays in 'provisioning_failed' for admin investigation.
 *
 * Idempotency : if a Server row exists with this idempotency_key AND has a
 * pelican_server_id, the job exits early (work already done). Prevents
 * duplicates on retry. The unique index on idempotency_key also blocks the
 * INSERT race if two workers pick the job simultaneously.
 */
class ProvisionServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    public function __construct(
        public readonly int $planId,
        public readonly int $userId,
        public readonly string $idempotencyKey,
        public readonly ?string $serverNameOverride = null,
        public readonly ?string $stripeSubscriptionId = null,
        public readonly ?string $paymentIntentId = null,
    ) {}

    public function handle(
        PortAllocator $portAllocator,
        EnvironmentResolver $environmentResolver,
        PelicanApplicationService $pelican,
        EnsurePelicanAccountAction $ensurePelicanAccount,
    ): void {
        $existing = Server::where('idempotency_key', $this->idempotencyKey)->first();
        if ($existing !== null && $existing->pelican_server_id !== null) {
            Log::info('ProvisionServerJob: idempotent hit, skipping', [
                'idempotency_key' => $this->idempotencyKey,
                'server_id' => $existing->id,
            ]);
            return;
        }

        $plan = ServerPlan::find($this->planId);
        $user = User::find($this->userId);

        if ($plan === null || $user === null) {
            $this->fail(new \RuntimeException("Plan #{$this->planId} or user #{$this->userId} not found"));
            return;
        }

        if (! $plan->isReadyToProvision()) {
            $this->fail(new \RuntimeException("Plan #{$plan->id} is not ready to provision"));
            return;
        }

        // Safety net: if the chain (LinkPelicanAccountJob → ProvisionServerJob)
        // was bypassed and the user has no Pelican account yet, ensure one
        // here. The action is idempotent and re-uses the same lock as the
        // dedicated link job. Pelican errors propagate so the standard retry
        // policy (3 tries, [60s, 300s, 900s]) covers transient outages.
        if ($user->pelican_user_id === null) {
            $ensurePelicanAccount->execute($user, 'provision-safety-net');
            $user->refresh();
        }

        $serverName = $this->serverNameOverride
            ?? sprintf('srv-%s-%s', Str::slug((string) ($plan->shop_plan_slug ?: $plan->name)), substr($this->idempotencyKey, 0, 8));

        $server = $existing ?? Server::create([
            'user_id' => $user->id,
            'name' => $serverName,
            'status' => 'provisioning',
            'egg_id' => $plan->egg_id,
            'plan_id' => $plan->id,
            'stripe_subscription_id' => $this->stripeSubscriptionId,
            'payment_intent_id' => $this->paymentIntentId,
            'idempotency_key' => $this->idempotencyKey,
        ]);

        // The client dashboard (/dashboard) lists servers via the
        // `server_user` pivot table, NOT the legacy `user_id` column on
        // `servers`. Without this row, the user owns the server in DB but
        // can't see it from their own panel. Idempotent : the unique
        // (user_id, server_id) constraint blocks duplicates if the job
        // retries after the local row was already created.
        $server->accessUsers()->syncWithoutDetaching([
            $user->id => ['role' => 'owner', 'permissions' => null],
        ]);

        try {
            // Resolve the local FK rows to their Pelican-side IDs. The
            // `eggs/nests/nodes` tables hold an auto-incremented LOCAL PK
            // (`id`) AND a mirror of the Pelican PK (`pelican_*_id`). Pelican
            // only accepts its own IDs — passing the local PKs returns 404
            // NotFoundHttpException on every endpoint that takes one.
            $node = $this->pickNode($plan);
            if ($node === null || $node->pelican_node_id === null) {
                throw new \RuntimeException('No node available for provisioning');
            }
            $egg = Egg::find($plan->egg_id);
            if ($egg === null || $egg->pelican_egg_id === null) {
                throw new \RuntimeException("Egg #{$plan->egg_id} not found locally or missing pelican_egg_id (run sync:eggs)");
            }

            // Effective count must include the highest offset referenced in
            // env_var_mapping — otherwise an admin who sets port_count=1
            // and a mapping at offset=2 would never get the +2 port reserved
            // and the variable would resolve to null. ServerPlan computes
            // this once, here we just hand it to the allocator which then
            // guarantees the WHOLE consecutive block is free in one pass.
            $allocations = $portAllocator->findConsecutiveFreePorts(
                nodeId: (int) $node->pelican_node_id,
                count: $plan->effectivePortCount(),
            );

            $eggDefaults = $pelican->getEggVariableDefaults((int) $egg->pelican_egg_id);
            $environment = $environmentResolver->resolve($plan, $allocations, $eggDefaults);

            $startup = $egg->startup;

            $defaultAllocation = $allocations[0];
            $additionalAllocations = array_map(fn ($a) => $a->id, array_slice($allocations, 1));

            $pelicanServer = $pelican->createServerAdvanced(new CreateServerRequest(
                name: $server->name,
                userId: (int) $user->pelican_user_id,
                eggId: (int) $egg->pelican_egg_id,
                // Pelican removed the nest concept from its API entirely (no
                // longer in EggTransformer, no longer in StoreServerRequest
                // rules). The DTO field is kept for back-compat but the
                // payload no longer carries it — pass 0 as a marker.
                nestId: 0,
                memoryMb: (int) ($plan->ram ?? 0),
                swapMb: (int) ($plan->swap_mb ?? 0),
                diskMb: (int) ($plan->disk ?? 0),
                ioWeight: (int) ($plan->io_weight ?? 500),
                cpuPercent: (int) ($plan->cpu ?? 0),
                featureLimitDatabases: (int) ($plan->feature_limits_databases ?? 0),
                featureLimitBackups: (int) ($plan->feature_limits_backups ?? 3),
                featureLimitAllocations: (int) ($plan->feature_limits_allocations ?? 1),
                environment: $environment,
                defaultAllocationId: $defaultAllocation->id,
                additionalAllocations: $additionalAllocations,
                dockerImage: $plan->docker_image ?: ($egg->docker_image ?: null),
                startup: $startup,
                startOnCompletion: (bool) $plan->start_on_completion,
                skipScripts: (bool) $plan->skip_install_script,
                oomDisabled: ! (bool) $plan->enable_oom_killer,
            ));

            $server->update([
                'pelican_server_id' => $pelicanServer->id,
                // Pelican's short UUID, used by every Client API call
                // (startup variables, websocket, files, console, etc.).
                // Without it the local row exists but the SPA can't talk
                // to Wings → 500 on /api/servers/{id}/startup, /websocket…
                'identifier' => $pelicanServer->identifier,
                'status' => 'active',
                'provisioning_error' => null,
            ]);

            Log::info('ProvisionServerJob: success', [
                'server_id' => $server->id,
                'pelican_server_id' => $pelicanServer->id,
                'plan_id' => $plan->id,
            ]);

            // Dispatch the ServerProvisioned event so listeners (notification
            // email, plugin hooks, analytics) react. Done AFTER the local
            // update so listeners observe the row in its final state.
            event(new ServerProvisioned($server->fresh(), $user));

            // Pelican is still installing in the background. Pick the polling
            // mode based on whether the Pelican webhook receiver is enabled :
            //   webhook ON  → SHORT mode (3 attempts, ~5 min cap). The
            //                 webhook normally fires ServerInstalled itself;
            //                 this is just a safety net in case the admin
            //                 misconfigured Pelican-side or the event is lost.
            //   webhook OFF → LONG mode (20 attempts, ~10 min cap). We're
            //                 the only signal Peregrine has.
            $webhookEnabled = (string) app(SettingsService::class)
                ->get('pelican_webhook_enabled', 'false');
            $monitorMode = ($webhookEnabled === 'true' || $webhookEnabled === '1')
                ? MonitorServerInstallationJob::MODE_SHORT
                : MonitorServerInstallationJob::MODE_LONG;

            MonitorServerInstallationJob::dispatch($server->id, $monitorMode)
                ->delay(now()->addSeconds(30));
        } catch (\Throwable $e) {
            $server->update([
                'status' => 'provisioning_failed',
                'provisioning_error' => Str::limit($e->getMessage(), 1000),
            ]);

            Log::warning('ProvisionServerJob: attempt failed, will retry', [
                'server_id' => $server->id,
                'attempt' => $this->attempts(),
                'message' => $e->getMessage(),
            ]);

            // Re-throw so the queue worker retries with backoff. The local
            // row stays as a marker so the admin sees what's happening, and
            // idempotency_key prevents double-creation on retry.
            throw $e;
        }
    }

    /**
     * Called by Laravel when retries are exhausted. Final marker for admin.
     */
    public function failed(\Throwable $e): void
    {
        $server = Server::where('idempotency_key', $this->idempotencyKey)->first();
        if ($server !== null) {
            $server->update([
                'status' => 'provisioning_failed',
                'provisioning_error' => 'Final failure after '.$this->tries.' attempts: '.Str::limit($e->getMessage(), 900),
            ]);
        }

        Log::error('ProvisionServerJob: terminal failure', [
            'idempotency_key' => $this->idempotencyKey,
            'plan_id' => $this->planId,
            'message' => $e->getMessage(),
        ]);
    }

    /**
     * Resolves the local Node row chosen for this plan. Returns the model
     * (not just the id) so the caller can pull `pelican_node_id` for API
     * calls — `node_id` / `default_node_id` / `allowed_node_ids` on the plan
     * are LOCAL FKs into `nodes.id`, not Pelican-side ids.
     */
    private function pickNode(ServerPlan $plan): ?Node
    {
        $localId = $plan->node_id
            ?? $plan->default_node_id
            ?? ($plan->auto_deploy && is_array($plan->allowed_node_ids) && count($plan->allowed_node_ids) > 0
                ? (int) $plan->allowed_node_ids[0]
                : null);

        return $localId !== null ? Node::find($localId) : null;
    }
}
