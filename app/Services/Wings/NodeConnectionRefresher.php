<?php

namespace App\Services\Wings;

use App\Models\Node;
use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Self-heal for stale mirrored daemon connection details (fqdn / scheme /
 * port). Mirrored nodes used to store Pelican's `daemon_listen` (the port
 * Wings BINDS to — 8080 by default) while the panel must dial
 * `daemon_connect`, so installs that synced before that mapping fix probe
 * the wrong port and report healthy nodes as unreachable forever.
 *
 * When a probe cannot connect, NodeHealthService asks this refresher to
 * re-fetch the node from Pelican; the probe is retried only when the
 * connection address actually changed. Pelican API cost is bounded to one
 * attempt per node per cooldown window (Cache::add is atomic, so
 * concurrent probes cannot stampede).
 */
class NodeConnectionRefresher
{
    private const COOLDOWN_SECONDS = 600;

    public function __construct(
        private readonly PelicanApplicationService $pelican,
    ) {}

    /**
     * True when the connection address changed — the caller should retry
     * its probe against the corrected address.
     */
    public function refresh(Node $node): bool
    {
        if ($node->pelican_node_id === null) {
            return false;
        }

        if (! Cache::add("wings_health:conn_refresh:{$node->id}", true, self::COOLDOWN_SECONDS)) {
            return false;
        }

        // Fully best-effort (the save() included): a failure here must let
        // the probe report `unreachable`, never 500 the page.
        try {
            $remote = $this->pelican->getNode($node->pelican_node_id);

            $before = $node->daemonBaseUrl();

            $node->forceFill([
                'fqdn' => $remote->fqdn,
                'scheme' => $remote->scheme,
                'daemon_listen' => $remote->daemonListen,
            ])->save();

            return $node->daemonBaseUrl() !== $before;
        } catch (\Throwable $e) {
            Log::warning('NodeConnectionRefresher: could not re-fetch node connection details from Pelican', [
                'node_id' => $node->id,
                'pelican_node_id' => $node->pelican_node_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
