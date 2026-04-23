<?php

namespace App\Jobs\Bridge;

use App\Models\Server;
use App\Models\User;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Mirrors a Pelican Server change into the local DB (Bridge Paymenter mode).
 *
 * Triggered by Pelican outgoing webhooks (`eloquent.created/updated/deleted`
 * on App\Models\Server, plus App\Events\Server\Installed). The controller
 * passes the raw Pelican payload snapshot — we treat it as the source of
 * truth (Pelican has no retry, so refetching could miss already-applied
 * changes).
 *
 * IMPORTANT : in Paymenter mode, no Peregrine event is dispatched (no
 * ServerProvisioned, no ServerSuspended). Paymenter sends every customer
 * email itself — Peregrine must NOT duplicate them.
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

    public function handle(PelicanApplicationService $pelican): void
    {
        if ($this->isDeletionEvent()) {
            $this->handleDeletion();
            return;
        }

        // Pelican's webhook payload omits the canonical `suspended_at` field
        // for `eloquent.updated` events — the only signal you'd get from the
        // payload alone is `status: null`, which (mis)maps to 'active' even
        // when the server is freshly suspended. Refetch the canonical state
        // from the Application API so the DTO's `isSuspended` flag is the
        // source of truth. The webhook still acts as the trigger; the API
        // call is the ground truth for the mapping.
        $apiSnapshot = null;
        try {
            $apiSnapshot = $pelican->getServer($this->pelicanServerId);
        } catch (\Throwable $e) {
            Log::warning('SyncServerFromPelicanWebhookJob: Pelican API refetch failed, falling back to webhook payload', [
                'pelican_server_id' => $this->pelicanServerId,
                'message' => $e->getMessage(),
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
     * Translate the `isSuspended` flag from the Pelican Application API
     * response into our local `servers.status` enum. We only know "is it
     * suspended or not" from this signal — the running/stopped/installing
     * runtime states are reported separately by Wings via the websocket
     * (see SyncServerStatusJob).
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

    private function handleDeletion(): void
    {
        $deleted = Server::where('pelican_server_id', $this->pelicanServerId)->delete();

        Log::info('SyncServerFromPelicanWebhookJob: server removed', [
            'pelican_server_id' => $this->pelicanServerId,
            'rows_deleted' => $deleted,
        ]);
    }

    /**
     * Translate Pelican's server status field into our local enum.
     *
     * Pelican exposes `status` as one of:
     *   null         → server is healthy/active
     *   'installing' → first-time install in progress
     *   'install_failed' / 'reinstall_failed' → install errored
     *   'suspended'  → admin/billing suspend
     *   'restoring_backup' → backup restore in progress
     *
     * We map these to the local `servers.status` enum values used elsewhere.
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
     * `php artisan sync:eggs`, so the lookup is a join on that column
     * (mirrors the convention used by ServerSync::syncSingleServer).
     *
     * If no local egg matches, auto-trigger a one-shot egg sync — same
     * pattern as ServerSync. After that, retry the lookup once. If still
     * unresolved, return null (server is created without an egg ref, admin
     * can patch later or it'll resolve on the next webhook event).
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
            // Egg not yet mirrored locally — try a one-shot sync, then retry.
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
