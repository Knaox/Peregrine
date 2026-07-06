<?php

declare(strict_types=1);

namespace App\Actions\Pelican;

use App\Models\Node;
use App\Models\Server;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves (and persists) which local Node hosts a server, plus its full
 * Pelican uuid — the two facts Wings health probing needs.
 *
 * Backfill path for servers provisioned before the node link existed, and
 * self-healing path after a node deletion/re-add (the FK is nullOnDelete).
 * Costs one Application-API call the first time, then reads from the local
 * row. If the node is not mirrored locally yet, it is upserted on the fly
 * so the admin never has to run a manual node sync first.
 *
 * Returns null when the server has no Pelican id yet (still provisioning)
 * or Pelican is unreachable — callers degrade to an "unknown" health state.
 */
final readonly class ResolveServerNodeAction
{
    public function __construct(
        private PelicanApplicationService $pelican,
    ) {}

    public function __invoke(Server $server): ?Node
    {
        if ($server->node_id !== null && $server->pelican_uuid !== null) {
            return $server->node;
        }

        if ($server->pelican_server_id === null) {
            return null;
        }

        // The whole backfill is best-effort: DB writes included, so a
        // half-migrated install or a Pelican outage degrades to the node we
        // already know locally (or unknown) instead of a 500 on the page.
        try {
            $pelicanServer = $this->pelican->getServer($server->pelican_server_id);

            $node = Node::where('pelican_node_id', $pelicanServer->nodeId)->first()
                ?? $this->mirrorNode($pelicanServer->nodeId);

            $server->forceFill([
                // Never erase a known placement with a failed mirror.
                'node_id' => $node?->id ?? $server->node_id,
                'pelican_uuid' => $pelicanServer->uuid !== '' ? $pelicanServer->uuid : $server->pelican_uuid,
            ])->save();

            return $node ?? $server->node;
        } catch (Throwable $e) {
            Log::info('ResolveServerNodeAction: could not resolve server node from Pelican', [
                'server_id' => $server->id,
                'pelican_server_id' => $server->pelican_server_id,
                'error' => $e->getMessage(),
            ]);

            // Partial link (node known, uuid still missing) — keep showing
            // the node name; health probing simply stays node-level.
            return $server->node_id !== null ? $server->node : null;
        }
    }

    private function mirrorNode(int $pelicanNodeId): ?Node
    {
        try {
            $remote = $this->pelican->getNode($pelicanNodeId);
        } catch (Throwable) {
            return null;
        }

        return Node::updateOrCreate(
            ['pelican_node_id' => $remote->id],
            [
                'name' => $remote->name,
                'fqdn' => $remote->fqdn,
                'scheme' => $remote->scheme,
                'daemon_listen' => $remote->daemonListen,
                'maintenance_mode' => $remote->maintenanceMode,
                'memory' => $remote->memory,
                'disk' => $remote->disk,
                'location' => $remote->location,
            ],
        );
    }
}
