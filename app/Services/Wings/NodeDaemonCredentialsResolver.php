<?php

namespace App\Services\Wings;

use App\Models\Node;
use App\Services\Pelican\PelicanHttpClient;
use Illuminate\Support\Facades\Log;

/**
 * Hydrates (and re-hydrates after a token rotation) the Wings daemon
 * credentials for a node.
 *
 * The `GET /api/application/nodes` payload hides the daemon token (Pelican
 * marks it hidden on its Node model), so we pull it from
 * `GET /api/application/nodes/{id}/configuration` — the endpoint meant for
 * automated Wings deployments. Called lazily by NodeHealthService, never on
 * the sync paths: webhooks stay cheap and a Pelican admin key without the
 * node-config permission only degrades health checks to `unknown`.
 */
class NodeDaemonCredentialsResolver
{
    public function __construct(
        private readonly PelicanHttpClient $http,
    ) {}

    public function ensure(Node $node): bool
    {
        return $node->hasDaemonToken() || $this->refresh($node);
    }

    /**
     * Force a re-fetch from Pelican (used after Wings rejects the token).
     */
    public function refresh(Node $node): bool
    {
        // Fully best-effort (the save() included): a failure here must
        // degrade the health check to `unknown`, never 500 the page —
        // e.g. a prod install whose nodes table misses the token columns.
        try {
            $response = $this->http->request()
                ->get("/api/application/nodes/{$node->pelican_node_id}/configuration")
                ->throw();

            $token = (string) $response->json('token', '');
            if ($token === '') {
                return false;
            }

            $node->forceFill([
                'daemon_token_id' => (string) $response->json('token_id', ''),
                'daemon_token' => $token,
            ])->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('NodeDaemonCredentialsResolver: could not refresh daemon credentials', [
                'node_id' => $node->id,
                'pelican_node_id' => $node->pelican_node_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
