<?php

namespace App\Jobs\Bridge;

use App\Events\Bridge\ServerInstalled;
use App\Events\Mirror\ServerMirrorChanged;
use App\Models\Server;
use App\Models\User;
use App\Services\Bridge\BridgeModeService;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Sync\EggResolver;
use App\Services\Sync\ServerStatusResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors a Pelican Server change into the local DB. Two paths : Shop-owned
 * servers get a strict whitelist update (only install-state + Pelican-derived
 * fields), Paymenter / admin-imported servers get a full upsert. Owner sync
 * is auto-triggered if the User row is missing locally.
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

        // Install-state is the ONLY status mutation Pelican is allowed on a
        // Shop-owned server — billing statuses belong to Stripe webhooks.
        $newStatus = $this->resolveInstallStatus($apiSnapshot, $previousStatus);
        if ($newStatus !== null && $newStatus !== $previousStatus) {
            $updates['status'] = $newStatus;
        }

        if ($updates !== []) {
            $server->update($updates);
        }

        // STRICT ORDER : status update MUST land before the event — listeners
        // see the row in its final state. Tested in ShopGuardTest.
        if (
            $previousStatus === 'provisioning'
            && ($updates['status'] ?? null) === 'active'
            && $bridgeMode->isShopStripe()
            && $server->user !== null
        ) {
            event(new ServerInstalled($server->fresh(), $server->user));
        }

        if ($updates !== []) {
            event(new ServerMirrorChanged((int) $server->id, ServerMirrorChanged::RESOURCE_SERVER, ServerMirrorChanged::ACTION_UPSERT, (int) $server->id));
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
                    ? $this->mapStatusFromApi($apiSnapshot)
                    : $this->mapPelicanStatus($this->payloadSnapshot),
                'paymenter_service_id' => $this->extractExternalId($this->payloadSnapshot),
                'egg_id' => $eggId,
            ], fn ($value) => $value !== null),
        );

        $server->accessUsers()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'permissions' => null],
        ]);

        event(new ServerMirrorChanged((int) $server->id, ServerMirrorChanged::RESOURCE_SERVER, ServerMirrorChanged::ACTION_UPSERT, (int) $server->id));

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

    private function resolveInstallStatus(?\App\Services\Pelican\DTOs\PelicanServer $apiSnapshot, string $previousStatus): ?string
    {
        return ServerStatusResolver::resolveInstallStatus($apiSnapshot, $previousStatus, $this->payloadSnapshot);
    }

    private function mapStatusFromApi(\App\Services\Pelican\DTOs\PelicanServer $snapshot): string
    {
        return ServerStatusResolver::mapStatusFromApi($snapshot);
    }

    private function isDeletionEvent(): bool
    {
        // Long form ("eloquent.deleted: App\Models\Server") and short form
        // ("deleted: Server") both end with "deleted: ...".
        $normalized = str_replace(' ', '', $this->eventType);
        return str_starts_with($normalized, 'deleted:') || str_contains($normalized, 'eloquent.deleted');
    }

    private function handleDeletion(BridgeModeService $bridgeMode): void
    {
        $server = Server::where('pelican_server_id', $this->pelicanServerId)->first();
        if ($server === null) {
            return;
        }

        // Shop-owned deletions belong to PurgeScheduledServerDeletionsJob ;
        // Pelican-side deletion is drift we log but don't act on.
        if ($this->isShopOwned($server) && $bridgeMode->isShopStripe()) {
            Log::warning('SyncServerFromPelicanWebhookJob: Pelican deleted a Shop-owned server, leaving local row in place for admin review', [
                'pelican_server_id' => $this->pelicanServerId,
                'local_server_id' => $server->id,
                'stripe_subscription_id' => $server->stripe_subscription_id,
            ]);
            return;
        }

        $serverLocalId = (int) $server->id;
        $server->delete();
        event(new ServerMirrorChanged($serverLocalId, ServerMirrorChanged::RESOURCE_SERVER, ServerMirrorChanged::ACTION_DELETE, $serverLocalId));

        Log::info('SyncServerFromPelicanWebhookJob: server removed', [
            'pelican_server_id' => $this->pelicanServerId,
            'local_server_id' => $serverLocalId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function mapPelicanStatus(array $data): string
    {
        return ServerStatusResolver::mapPelicanStatus($data);
    }

    /** @param  array<string, mixed>  $data */
    private function extractExternalId(array $data): ?string
    {
        // Pelican stores Paymenter's service id in `external_id`.
        $value = $data['external_id'] ?? null;
        return ($value === null || $value === '') ? null : (string) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveLocalEggId(array $data): ?int
    {
        return EggResolver::resolveLocalEggId($data, $this->pelicanServerId);
    }
}
