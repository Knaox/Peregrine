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
        try {
            $response = $this->http->request()
                ->get("/api/application/nodes/{$node->pelican_node_id}/configuration")
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('NodeDaemonCredentialsResolver: could not fetch node configuration', [
                'node_id' => $node->id,
                'pelican_node_id' => $node->pelican_node_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $token = (string) $response->json('token', '');
        if ($token === '') {
            return false;
        }

        $node->forceFill([
            'daemon_token_id' => (string) $response->json('token_id', ''),
            'daemon_token' => $token,
        ])->save();

        return true;
    }
}
