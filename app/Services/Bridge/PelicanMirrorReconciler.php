<?php

namespace App\Services\Bridge;

use App\Events\Mirror\ServerMirrorChanged;
use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Models\Server;
use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Sync\ServerStatusResolver;
use Illuminate\Support\Facades\Log;

/**
 * Diffs Peregrine's local Server mirror against Pelican's canonical server
 * list and dispatches sync jobs to fill the gaps.
 *
 * Three reconciliation buckets :
 *  1. Pelican-side servers absent locally → upsert via sync job
 *  2. Local servers absent Pelican-side → delete + broadcast
 *  3. Local servers whose status drifted from Pelican (suspension,
 *     install lifecycle) → re-sync via the sync job. This branch is what
 *     catches updates that Pelican ships through broken Eloquent CRUD
 *     webhooks (`(array) $model` instead of `$model->toArray()`, no
 *     model id) — the controller dispatches the reconciler, the
 *     reconciler diffs by status, and the sync job lands the change
 *     locally + broadcasts the mirror.changed event.
 *
 * Used in two paths in Paymenter mode :
 *  1. cron (every 5 min via SyncServerStatusJob) — periodic safety net
 *  2. on-demand fallback (ReconcilePelicanMirrorJob) — fired by
 *     PelicanWebhookController on broken Server webhook payloads.
 */
final class PelicanMirrorReconciler
{
    public function __construct(
        private readonly PelicanApplicationService $pelican,
        private readonly BridgeModeService $bridgeMode,
    ) {}

    public function reconcile(): void
    {
        if (! $this->bridgeMode->isPaymenter()) {
            return;
        }

        try {
            $pelicanServers = $this->pelican->listServers();
        } catch (\Throwable $e) {
            Log::warning('PelicanMirrorReconciler: listServers failed', [
                'message' => $e->getMessage(),
            ]);
            return;
        }

        $pelicanIds = collect($pelicanServers)->keyBy('id');
        $localServers = Server::whereNotNull('pelican_server_id')->get()->keyBy('pelican_server_id');

        foreach ($pelicanIds as $id => $pelicanServer) {
            $local = $localServers->get($id);

            if ($local === null) {
                $this->dispatchSync($pelicanServer);
                continue;
            }

            if ($this->statusDrifted($local, $pelicanServer)) {
                $this->dispatchSync($pelicanServer);
            }
        }

        $orphanIds = $localServers->keys()
            ->diff($pelicanIds->keys())
            ->all();

        foreach ($orphanIds as $orphanPelicanId) {
            $this->deleteOrphan($localServers->get($orphanPelicanId));
        }
    }

    /**
     * True when the local row's status is one of the App-API-controlled
     * states (suspended / provisioning / provisioning_failed / active) AND
     * Pelican's view disagrees. We never override runtime states (running /
     * stopped / offline) here — those are managed by syncRuntimeStatuses.
     */
    private function statusDrifted(Server $local, PelicanServer $pelican): bool
    {
        $expected = ServerStatusResolver::mapStatusFromApi($pelican);
        $current = (string) $local->status;

        if ($current === $expected) {
            return false;
        }

        // Drift only matters when transitioning into / out of an
        // App-API-controlled state. A `running` row staying `running` while
        // Pelican answers `active` (idle) is not drift — runtime state is
        // a strict superset and the runtime sync owns it.
        $appControlled = ['suspended', 'provisioning', 'provisioning_failed'];

        return in_array($expected, $appControlled, true)
            || in_array($current, $appControlled, true);
    }

    private function dispatchSync(PelicanServer $pelicanServer): void
    {
        SyncServerFromPelicanWebhookJob::dispatch(
            eventType: 'reconcile: Server',
            pelicanServerId: $pelicanServer->id,
            payloadSnapshot: [
                'id' => $pelicanServer->id,
                'identifier' => $pelicanServer->identifier,
                'name' => $pelicanServer->name,
                'user' => $pelicanServer->userId,
                'node_id' => $pelicanServer->nodeId,
                'egg_id' => $pelicanServer->eggId,
                'suspended' => $pelicanServer->isSuspended,
                'status' => $pelicanServer->status,
                'updated_at' => now()->toDateTimeString(),
            ],
        );
    }

    private function deleteOrphan(Server $server): void
    {
        $localId = (int) $server->id;
        $accessUserIds = $server->accessUsers()->pluck('users.id')->all();

        $server->delete();

        event(new ServerMirrorChanged(
            $localId,
            ServerMirrorChanged::RESOURCE_SERVER,
            ServerMirrorChanged::ACTION_DELETE,
            $localId,
            $accessUserIds,
        ));

        Log::info('PelicanMirrorReconciler: orphan local server removed', [
            'local_server_id' => $localId,
            'pelican_server_id' => $server->pelican_server_id,
        ]);
    }
}
