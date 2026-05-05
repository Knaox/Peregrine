<?php

namespace App\Services\Bridge;

use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Models\Server;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Support\Facades\Log;

/**
 * Diffs Peregrine's local Server mirror against Pelican's canonical server
 * list and dispatches sync jobs to fill the gaps.
 *
 * Used in two paths in Paymenter mode :
 *  1. cron (every 5 min via SyncServerStatusJob) — periodic safety net
 *  2. on-demand fallback (ReconcilePelicanMirrorJob) — fired by
 *     PelicanWebhookController whenever Pelican ships a malformed Eloquent
 *     CRUD webhook for a Server (a known Pelican bug where `(array) $model`
 *     is shipped instead of `$model->toArray()`, leaving the receiver with
 *     no model id to act on)
 *
 * Mirror creation is delegated to SyncServerFromPelicanWebhookJob, which
 * already knows how to map Pelican's lifecycle (`installing` /
 * `install_failed` / null) to local statuses (`provisioning` /
 * `provisioning_failed` / `active`) via ServerStatusResolver.
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
        $localIds = Server::whereNotNull('pelican_server_id')->pluck('pelican_server_id')->all();

        foreach ($pelicanIds as $id => $pelicanServer) {
            if (in_array($id, $localIds, true)) {
                continue;
            }
            SyncServerFromPelicanWebhookJob::dispatch(
                eventType: 'reconcile: Server',
                pelicanServerId: $id,
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

        $orphans = array_diff($localIds, $pelicanIds->keys()->all());
        if ($orphans !== []) {
            Server::whereIn('pelican_server_id', $orphans)->delete();
            Log::info('PelicanMirrorReconciler: orphan local servers removed', [
                'pelican_server_ids' => array_values($orphans),
            ]);
        }
    }
}
