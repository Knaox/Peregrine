<?php

namespace App\Jobs;

use App\Jobs\Bridge\SyncServerFromPelicanWebhookJob;
use App\Models\Server;
use App\Services\Bridge\BridgeModeService;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Pelican\PelicanClientService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncServerStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        PelicanClientService $clientService,
        BridgeModeService $bridgeMode,
        PelicanApplicationService $pelican,
    ): void {
        $this->syncRuntimeStatuses($clientService);

        // Bridge Paymenter mode: Pelican does not retry failed webhooks, so
        // we periodically reconcile the local Server table against Pelican's
        // canonical list to catch any missed create / delete event.
        if ($bridgeMode->isPaymenter()) {
            $this->reconcilePaymenterMirror($pelican);
        }
    }

    private function syncRuntimeStatuses(PelicanClientService $clientService): void
    {
        $servers = Server::whereNotNull('identifier')->get();

        foreach ($servers as $server) {
            try {
                $resources = $clientService->getServerResources($server->identifier);

                $newStatus = match ($resources->state) {
                    'running', 'starting' => 'running',
                    'stopping', 'stopped' => 'stopped',
                    'offline' => 'offline',
                    default => $server->status,
                };

                if ($server->status !== $newStatus && ! in_array($server->status, ['suspended', 'terminated'], true)) {
                    $server->update(['status' => $newStatus]);
                }
            } catch (\Throwable) {
                // If API call fails, mark as offline (unless suspended/terminated)
                if (! in_array($server->status, ['suspended', 'terminated'], true)) {
                    $server->update(['status' => 'offline']);
                }
            }
        }
    }

    /**
     * Reconcile the local Server mirror with Pelican's canonical list.
     *
     * Catches the cases Pelican's webhook may have missed (delivery failure,
     * panel restart, brief outage of Peregrine). Cheap : single GET on the
     * full Pelican server list, diffs in-memory.
     */
    private function reconcilePaymenterMirror(PelicanApplicationService $pelican): void
    {
        try {
            $pelicanServers = $pelican->listServers();
        } catch (\Throwable $e) {
            Log::warning('SyncServerStatusJob: Pelican listServers failed during reconciliation', [
                'message' => $e->getMessage(),
            ]);
            return;
        }

        $pelicanIds = collect($pelicanServers)->keyBy('id');
        $localIds = Server::whereNotNull('pelican_server_id')->pluck('pelican_server_id')->all();

        // Pelican-side servers absent locally → simulate a created event.
        foreach ($pelicanIds as $id => $pelicanServer) {
            if (in_array($id, $localIds, true)) {
                continue;
            }
            SyncServerFromPelicanWebhookJob::dispatch(
                eventType: 'eloquent.created: App\\Models\\Server',
                pelicanServerId: $id,
                payloadSnapshot: [
                    'id' => $pelicanServer->id,
                    'identifier' => $pelicanServer->identifier,
                    'name' => $pelicanServer->name,
                    'user' => $pelicanServer->userId,
                    'node_id' => $pelicanServer->nodeId,
                    'egg_id' => $pelicanServer->eggId,
                    'suspended' => $pelicanServer->isSuspended,
                    'updated_at' => now()->toDateTimeString(),
                ],
            );
        }

        // Local servers absent Pelican-side → drop the orphan row.
        $orphans = array_diff($localIds, $pelicanIds->keys()->all());
        if (! empty($orphans)) {
            Server::whereIn('pelican_server_id', $orphans)->delete();
            Log::info('SyncServerStatusJob: orphan local servers removed during paymenter reconciliation', [
                'pelican_server_ids' => array_values($orphans),
            ]);
        }
    }
}
