<?php

namespace App\Jobs\Bridge;

use App\Events\Bridge\ServerInstalled;
use App\Events\Mirror\ServerMirrorChanged;
use App\Models\Server;
use App\Models\User;
use App\Services\Integrations\IntegrationStatusService;
use App\Services\Pelican\DTOs\PelicanServer;
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
        IntegrationStatusService $integrations,
    ): void {
        if ($this->isDeletionEvent()) {
            $this->handleDeletion();

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

        if ($existing !== null && $this->isStripeManaged($existing)) {
            $this->updateShopOwned($existing, $apiSnapshot);

            return;
        }

        // Not Stripe-managed (orchestrator-driven mirror, admin-imported, or
        // hand-created in Pelican) → full upsert.
        $this->fullUpsert($existing, $apiSnapshot, $integrations);
    }

    /**
     * Shop owns this server — apply only the install-status delta + a few
     * pure-mirror fields. Never touch ownership, name, or billing status.
     */
    private function updateShopOwned(
        Server $server,
        ?PelicanServer $apiSnapshot,
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
        // We've already passed the `isStripeManaged()` check in handle() :
        // the server carries a stripe_subscription_id, meaning Stripe drives
        // its lifecycle. Fire the install-completed email regardless of mode.
        if (
            $previousStatus === 'provisioning'
            && ($updates['status'] ?? null) === 'active'
            && $server->user !== null
        ) {
            event(new ServerInstalled($server->fresh(), $server->user));
        }

        if ($updates !== []) {
            event(new ServerMirrorChanged((int) $server->id, ServerMirrorChanged::RESOURCE_SERVER, ServerMirrorChanged::ACTION_UPSERT, (int) $server->id, $server->accessUsers()->pluck('users.id')->all()));
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
        ?PelicanServer $apiSnapshot,
        IntegrationStatusService $integrations,
    ): void {
        // When Stripe is wired, an unknown pelican_server_id is ambiguous :
        //   (a) provisioning race — ProvisionServerJob created the local row
        //       (status='provisioning', carrying stripe_subscription_id) but
        //       hasn't written back the pelican_server_id yet, so we can't
        //       find it. Releasing lets that job land the id ; on retry the
        //       row is found and routed to updateShopOwned().
        //   (b) genuinely hand-created in Pelican — no local row will ever
        //       appear. Such a server has no subscription, so Pelican is its
        //       source of truth and we must mirror it.
        // We can't tell them apart immediately, so we release-and-retry and
        // only fall through to creation on the last attempt (by then the
        // provisioning write-back has had the full backoff window to land).
        if ($existing === null && $integrations->hasStripeConfigured()) {
            if ($this->attempts() < $this->tries) {
                Log::info('SyncServerFromPelicanWebhookJob: unknown server under Stripe, releasing to disambiguate provisioning race', [
                    'pelican_server_id' => $this->pelicanServerId,
                    'event_type' => $this->eventType,
                    'attempt' => $this->attempts(),
                ]);
                $this->release(60);

                return;
            }

            Log::info('SyncServerFromPelicanWebhookJob: unknown server still absent after retries, mirroring as hand-created (no subscription)', [
                'pelican_server_id' => $this->pelicanServerId,
                'event_type' => $this->eventType,
            ]);
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

        event(new ServerMirrorChanged((int) $server->id, ServerMirrorChanged::RESOURCE_SERVER, ServerMirrorChanged::ACTION_UPSERT, (int) $server->id, $server->accessUsers()->pluck('users.id')->all()));

        Log::info('SyncServerFromPelicanWebhookJob: server mirrored', [
            'pelican_server_id' => $this->pelicanServerId,
            'event_type' => $this->eventType,
            'local_server_id' => $server->id,
            'status' => $server->status,
            'source' => $apiSnapshot !== null ? 'api' : 'payload_fallback',
        ]);
    }

    /**
     * A server is "Stripe-managed" when it carries a Stripe subscription id.
     * Those servers have their lifecycle driven by Stripe (create via the
     * checkout flow, delete via the cancellation → purge pipeline), so
     * Pelican is NOT allowed to create or delete them out of band — only the
     * install-state delta is mirrored. Every other server (no subscription :
     * admin-imported, hand-created in Pelican, orchestrator mirror) follows
     * Pelican as source of truth. `stripe_subscription_id` is never reset to
     * null while the row lives, so this stays true until the legitimate purge
     * removes the row entirely.
     */
    private function isStripeManaged(Server $server): bool
    {
        return $server->stripe_subscription_id !== null;
    }

    private function resolveInstallStatus(?PelicanServer $apiSnapshot, string $previousStatus): ?string
    {
        return ServerStatusResolver::resolveInstallStatus($apiSnapshot, $previousStatus, $this->payloadSnapshot);
    }

    private function mapStatusFromApi(PelicanServer $snapshot): string
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

    private function handleDeletion(): void
    {
        $server = Server::where('pelican_server_id', $this->pelicanServerId)->first();
        if ($server === null) {
            return;
        }

        // Stripe-managed deletions belong to PurgeScheduledServerDeletionsJob.
        // A Pelican-side deletion arriving for a server that still carries a
        // Stripe subscription is drift (someone hand-deleted it in the Pelican
        // panel while billing is live). We refuse to mirror it so the admin
        // can investigate. Servers without a subscription follow Pelican and
        // are removed locally below.
        if ($this->isStripeManaged($server)) {
            Log::warning('SyncServerFromPelicanWebhookJob: Pelican deleted a Stripe-managed server, leaving local row in place for admin review', [
                'pelican_server_id' => $this->pelicanServerId,
                'local_server_id' => $server->id,
                'stripe_subscription_id' => $server->stripe_subscription_id,
            ]);

            return;
        }

        $serverLocalId = (int) $server->id;
        // Capture the pivot BEFORE delete — the cascade clears access_users
        // server-side and we need the ids to broadcast on each user channel
        // so list pages drop the row in real-time.
        $accessUserIds = $this->collectAccessUserIds($server);
        $server->delete();
        event(new ServerMirrorChanged(
            $serverLocalId,
            ServerMirrorChanged::RESOURCE_SERVER,
            ServerMirrorChanged::ACTION_DELETE,
            $serverLocalId,
            $accessUserIds,
        ));

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

    /**
     * Snapshot the pivot BEFORE the deletion cascade wipes `server_user`,
     * so the `mirror.changed` (action=delete) broadcast still fans out on
     * every owner / subuser channel — without this, the dashboard / detail
     * page would only learn about the removal at the next 5-min staleTime
     * refetch (or never if the user stays put). Falls back to the legacy
     * `user_id` column for pre-pivot servers (same defensive merge as
     * `BroadcastsServerMirror::broadcastServerMirrorChanged()`).
     *
     * @return array<int, int>
     */
    private function collectAccessUserIds(Server $server): array
    {
        $ids = $server->accessUsers()->pluck('users.id')->all();
        $legacyOwnerId = (int) ($server->user_id ?? 0);
        if ($legacyOwnerId > 0 && ! in_array($legacyOwnerId, $ids, true)) {
            $ids[] = $legacyOwnerId;
        }

        return $ids;
    }
}
