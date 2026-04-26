<?php

namespace App\Jobs\Bridge;

use App\Events\Bridge\ServerInstalled;
use App\Models\Server;
use App\Models\User;
use App\Services\Bridge\BridgeModeService;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors a Pelican Server change into the local DB.
 *
 * Triggered by Pelican outgoing webhooks (`eloquent.created/updated/deleted`
 * on App\Models\Server, plus App\Events\Server\Installed). The controller
 * passes the raw Pelican payload snapshot — we use it as the trigger and
 * refetch the canonical state from the Pelican Application API so the DTO's
 * `isSuspended` flag is the source of truth (Pelican's webhook payload omits
 * the canonical `suspended_at` field for `eloquent.updated` events).
 *
 * Behaviour depends on whether the Server is Shop-owned :
 *
 *   Shop-owned (has `stripe_subscription_id` OR `plan_id`)
 *     - The Shop is source of truth for ownership, name, billing status.
 *     - Pelican is only allowed to fill the gaps the Shop doesn't have :
 *       `pelican_server_id` (already set), `identifier`, `egg_id`,
 *       `paymenter_service_id`, AND the install transition
 *       `provisioning` → `active` / `provisioning_failed`.
 *     - Never touches `user_id`, `name`, billing-status (`suspended` /
 *       `terminated`), `plan_id`, `stripe_subscription_id`.
 *     - On `provisioning` → `active` transition AND shop_stripe mode, fires
 *       `ServerInstalled` so the "your server is playable" email goes out.
 *       Strict order : update status FIRST, fire event SECOND.
 *
 *   Not Shop-owned (Paymenter mode, or admin-imported servers)
 *     - Pelican is the source of truth → full upsert on every field.
 *     - No Peregrine event is dispatched (Paymenter sends its own emails).
 *
 *   Server doesn't exist locally + Shop+Stripe mode
 *     - Skip with a warning. The local row is supposed to be created by
 *       ProvisionServerJob (Stripe webhook). If a Pelican webhook arrives
 *       first, ignore — the next webhook (after the Stripe flow caught up)
 *       will succeed via the upsert path.
 *
 *   Server doesn't exist locally + Paymenter mode
 *     - Create it (existing behaviour).
 *
 * If the owner doesn't exist locally yet, dispatch SyncUserFromPelicanWebhookJob
 * and rely on retry to pick up the freshly-synced user. Three retries with
 * a short backoff cover the typical "user just created" race.
 */
class SyncServerFromPelicanWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $payloadSnapshot
     */
    public function __construct(
        public readonly string $eventType,
        public readonly int $pelicanServerId,
        public readonly array $payloadSnapshot,
    ) {}

    public function handle(
        PelicanApplicationService $pelican,
        BridgeModeService $bridgeMode,
    ): void {
        if ($this->isDeletionEvent()) {
            $this->handleDeletion($bridgeMode);
            return;
        }

        $apiSnapshot = null;
        try {
            $apiSnapshot = $pelican->getServer($this->pelicanServerId);
        } catch (\Throwable $e) {
            Log::warning('SyncServerFromPelicanWebhookJob: Pelican API refetch failed, falling back to webhook payload', [
                'pelican_server_id' => $this->pelicanServerId,
                'message' => $e->getMessage(),
            ]);
        }

        $existing = Server::where('pelican_server_id', $this->pelicanServerId)->first();

        if ($existing !== null && $this->isShopOwned($existing)) {
            $this->updateShopOwned($existing, $apiSnapshot, $bridgeMode);
            return;
        }

        // Not Shop-owned (Paymenter mirror or admin-imported) → full upsert.
        $this->fullUpsert($existing, $apiSnapshot, $bridgeMode);
    }

    /**
     * Shop owns this server — apply only the install-status delta + a few
     * pure-mirror fields. Never touch ownership, name, or billing status.
     */
    private function updateShopOwned(
        Server $server,
        ?\App\Services\Pelican\DTOs\PelicanServer $apiSnapshot,
        BridgeModeService $bridgeMode,
    ): void {
        $previousStatus = (string) $server->status;

        $updates = array_filter([
            'identifier' => $apiSnapshot?->identifier
                ?? (string) ($this->payloadSnapshot['identifier'] ?? $this->payloadSnapshot['uuid_short'] ?? '') ?: null,
            'paymenter_service_id' => $this->extractExternalId($this->payloadSnapshot),
            'egg_id' => $this->resolveLocalEggId(
                $apiSnapshot !== null
                    ? ['egg_id' => $apiSnapshot->eggId]
                    : $this->payloadSnapshot,
            ),
        ], fn ($v) => $v !== null);

        // Install-state transition is the ONLY status mutation Pelican is
        // allowed on a Shop-owned server. We never overwrite billing statuses
        // (`suspended` / `terminated`) — those belong to Stripe webhooks.
        $newStatus = $this->resolveInstallStatus($apiSnapshot, $previousStatus);
        if ($newStatus !== null && $newStatus !== $previousStatus) {
            $updates['status'] = $newStatus;
        }

        if ($updates !== []) {
            $server->update($updates);
        }

        // STRICT ORDER : status update above MUST land before the event so any
        // listener (e.g. SendServerInstalledNotification) sees the row in its
        // final state. If you reorder these two, race conditions appear and
        // double-emails / stale-status emails follow. Tested in
        // SyncServerFromPelicanWebhookJobShopGuardTest.
        if (
            $previousStatus === 'provisioning'
            && ($updates['status'] ?? null) === 'active'
            && $bridgeMode->isShopStripe()
            && $server->user !== null
        ) {
            event(new ServerInstalled($server->fresh(), $server->user));
        }

        Log::info('SyncServerFromPelicanWebhookJob: shop-owned server updated', [
            'pelican_server_id' => $this->pelicanServerId,
            'event_type' => $this->eventType,
            'local_server_id' => $server->id,
            'previous_status' => $previousStatus,
            'new_status' => $updates['status'] ?? $previousStatus,
            'fields_updated' => array_keys($updates),
        ]);
    }

    /**
     * Pelican-as-source-of-truth path : Paymenter mirror, or admin-imported
     * servers in shop_stripe / disabled mode. In shop_stripe mode, if the
     * server doesn't exist locally we skip — Pelican should not be allowed
     * to create Shop servers out of band.
     */
    private function fullUpsert(
        ?Server $existing,
        ?\App\Services\Pelican\DTOs\PelicanServer $apiSnapshot,
        BridgeModeService $bridgeMode,
    ): void {
        if ($existing === null && $bridgeMode->isShopStripe()) {
            Log::info('SyncServerFromPelicanWebhookJob: skipping unknown server in shop_stripe mode (Shop will create it)', [
                'pelican_server_id' => $this->pelicanServerId,
                'event_type' => $this->eventType,
            ]);
            return;
        }

        $ownerId = $apiSnapshot?->userId
            ?? (int) ($this->payloadSnapshot['user'] ?? $this->payloadSnapshot['owner_id'] ?? 0);
        if ($ownerId === 0) {
            Log::warning('SyncServerFromPelicanWebhookJob: missing owner id in payload', [
                'pelican_server_id' => $this->pelicanServerId,
                'event_type' => $this->eventType,
            ]);
            return;
        }

        $owner = User::where('pelican_user_id', $ownerId)->first();
        if ($owner === null) {
            // User is unknown locally. Trigger a user sync and retry — the
            // backoff gives Pelican / queue worker time to finish.
            SyncUserFromPelicanWebhookJob::dispatch($ownerId);
            $this->release(60);
            return;
        }

        $eggId = $this->resolveLocalEggId(
            $apiSnapshot !== null
                ? ['egg_id' => $apiSnapshot->eggId]
                : $this->payloadSnapshot,
        );

        $server = Server::updateOrCreate(
            ['pelican_server_id' => $this->pelicanServerId],
            array_filter([
                'user_id' => $owner->id,
                'name' => $apiSnapshot?->name
                    ?? (string) ($this->payloadSnapshot['name'] ?? 'server-'.$this->pelicanServerId),
                'identifier' => $apiSnapshot?->identifier
                    ?? (string) ($this->payloadSnapshot['identifier'] ?? $this->payloadSnapshot['uuid_short'] ?? ''),
                'status' => $apiSnapshot !== null
                    ? $this->mapStatusFromApi($apiSnapshot->isSuspended)
                    : $this->mapPelicanStatus($this->payloadSnapshot),
                'paymenter_service_id' => $this->extractExternalId($this->payloadSnapshot),
                'egg_id' => $eggId,
            ], fn ($value) => $value !== null),
        );

        $server->accessUsers()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'permissions' => null],
        ]);

        Log::info('SyncServerFromPelicanWebhookJob: server mirrored', [
            'pelican_server_id' => $this->pelicanServerId,
            'event_type' => $this->eventType,
            'local_server_id' => $server->id,
            'status' => $server->status,
            'source' => $apiSnapshot !== null ? 'api' : 'payload_fallback',
        ]);
    }

    /**
     * A server is "Shop-owned" when the Stripe webhook flow has tagged it
     * with either a subscription id or a plan id. In those cases the Shop is
     * source of truth for everything except install state.
     */
    private function isShopOwned(Server $server): bool
    {
        return $server->stripe_subscription_id !== null
            || $server->plan_id !== null;
    }

    /**
     * Compute the install-related status delta Pelican is allowed to apply
     * on a Shop-owned server. Returns null when no transition should happen.
     *
     * Allowed transitions :
     *   provisioning → active                (install finished)
     *   provisioning → provisioning_failed   (install errored)
     *
     * Anything else (suspended, terminated, runtime states from Wings) is
     * the Shop's / billing's / Wings' responsibility — we don't touch it.
     */
    private function resolveInstallStatus(
        ?\App\Services\Pelican\DTOs\PelicanServer $apiSnapshot,
        string $previousStatus,
    ): ?string {
        if ($previousStatus !== 'provisioning') {
            return null;
        }

        if ($apiSnapshot !== null) {
            // PelicanServer DTO exposes `installFailed()` / `isInstalling()`.
            if ($apiSnapshot->installFailed()) {
                return 'provisioning_failed';
            }
            if ($apiSnapshot->isInstalling()) {
                return null;
            }
            // Install finished, server is healthy.
            return 'active';
        }

        // No API snapshot — fall back to payload-only mapping.
        $payloadStatus = $this->payloadSnapshot['status'] ?? null;
        return match ($payloadStatus) {
            'install_failed', 'reinstall_failed' => 'provisioning_failed',
            'installing' => null,
            null, '' => 'active',
            default => null,
        };
    }

    /**
     * Translate the `isSuspended` flag from the Pelican Application API
     * response into our local `servers.status` enum. Used only on the
     * full-upsert path (Paymenter / admin-imported), where Pelican is the
     * source of truth for the lifecycle status.
     */
    private function mapStatusFromApi(bool $isSuspended): string
    {
        return $isSuspended ? 'suspended' : 'active';
    }

    private function isDeletionEvent(): bool
    {
        $normalized = str_replace(' ', '', $this->eventType);

        // Long form ("eloquent.deleted: App\Models\Server") and short form
        // (Pelican UI label "deleted: Server") both end with "deleted: ...".
        return str_starts_with($normalized, 'deleted:')
            || str_contains($normalized, 'eloquent.deleted');
    }

    private function handleDeletion(BridgeModeService $bridgeMode): void
    {
        $server = Server::where('pelican_server_id', $this->pelicanServerId)->first();
        if ($server === null) {
            return;
        }

        // Shop-owned servers are deleted by the Stripe-side
        // `PurgeScheduledServerDeletionsJob` flow (after the grace period).
        // Pelican-side deletion of a Shop-owned server is treated as drift
        // we record but don't act on — admin investigates manually.
        if ($this->isShopOwned($server) && $bridgeMode->isShopStripe()) {
            Log::warning('SyncServerFromPelicanWebhookJob: Pelican deleted a Shop-owned server, leaving local row in place for admin review', [
                'pelican_server_id' => $this->pelicanServerId,
                'local_server_id' => $server->id,
                'stripe_subscription_id' => $server->stripe_subscription_id,
            ]);
            return;
        }

        $server->delete();

        Log::info('SyncServerFromPelicanWebhookJob: server removed', [
            'pelican_server_id' => $this->pelicanServerId,
            'local_server_id' => $server->id,
        ]);
    }

    /**
     * Translate Pelican's server status field into our local enum. Used on
     * the full-upsert path only (Paymenter / admin-imported).
     *
     * @param  array<string, mixed>  $data
     */
    private function mapPelicanStatus(array $data): string
    {
        $isSuspended = (bool) ($data['suspended'] ?? false);
        $status = $data['status'] ?? null;

        if ($isSuspended || $status === 'suspended') {
            return 'suspended';
        }

        return match ($status) {
            'installing' => 'provisioning',
            'install_failed', 'reinstall_failed' => 'provisioning_failed',
            null, '' => 'active',
            default => (string) $status,
        };
    }

    /**
     * Pelican stores Paymenter's service id in `external_id`. Surface it
     * locally for audit / support flows.
     *
     * @param  array<string, mixed>  $data
     */
    private function extractExternalId(array $data): ?string
    {
        $value = $data['external_id'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * Resolve the local egg id from the Pelican payload.
     *
     * Pelican ships the egg as `egg_id` at the top of the Server payload —
     * that integer is Pelican's own egg id, NOT our local `eggs.id`. We
     * mirror Pelican's id in our `eggs.pelican_egg_id` column via
     * `php artisan sync:eggs`, so the lookup is a join on that column.
     *
     * If no local egg matches, auto-trigger a one-shot egg sync. After that,
     * retry the lookup once. If still unresolved, return null.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveLocalEggId(array $data): ?int
    {
        $pelicanEggId = $data['egg_id'] ?? $data['egg'] ?? null;
        if ($pelicanEggId === null || $pelicanEggId === '') {
            return null;
        }

        $pelicanEggId = (int) $pelicanEggId;
        $localEggId = \App\Models\Egg::where('pelican_egg_id', $pelicanEggId)->value('id');

        if ($localEggId === null) {
            try {
                app(\App\Services\Sync\InfrastructureSync::class)->syncEggs();
                $localEggId = \App\Models\Egg::where('pelican_egg_id', $pelicanEggId)->value('id');
            } catch (\Throwable $e) {
                Log::warning('SyncServerFromPelicanWebhookJob: egg auto-sync failed', [
                    'pelican_server_id' => $this->pelicanServerId,
                    'pelican_egg_id' => $pelicanEggId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($localEggId === null) {
            Log::info('SyncServerFromPelicanWebhookJob: egg not resolvable locally', [
                'pelican_server_id' => $this->pelicanServerId,
                'pelican_egg_id' => $pelicanEggId,
            ]);
        }

        return $localEggId !== null ? (int) $localEggId : null;
    }
}
