<?php

namespace App\Services\Pelican;

use App\Services\Pelican\DTOs\CreateServerRequest;
use App\Services\Pelican\DTOs\PelicanAllocation;
use App\Services\Pelican\DTOs\PelicanEgg;
use App\Services\Pelican\DTOs\PelicanNode;
use App\Services\Pelican\DTOs\PelicanServer;
use Illuminate\Http\Client\RequestException;

/**
 * Pelican Application API — servers, nodes, eggs. Everything related to
 * the infrastructure beyond the user domain.
 */
class PelicanInfrastructureClient
{
    public function __construct(private PelicanHttpClient $http) {}

    // Servers -------------------------------------------------------------

    /**
     * @throws RequestException
     */
    public function createServer(
        int $userId,
        int $eggId,
        int $nestId,
        int $ram,
        int $cpu,
        int $disk,
        int $nodeId,
        string $name,
    ): PelicanServer {
        $response = $this->http->request()
            ->post('/api/application/servers', [
                'name' => $name,
                'user' => $userId,
                'egg' => $eggId,
                'nest' => $nestId,
                'docker_image' => '~',
                'startup' => '~',
                'limits' => [
                    'memory' => $ram,
                    'swap' => 0,
                    'disk' => $disk,
                    'io' => 500,
                    'cpu' => $cpu,
                ],
                'feature_limits' => [
                    'databases' => 0,
                    'allocations' => 1,
                    'backups' => 0,
                ],
                'deploy' => [
                    'locations' => [],
                    'dedicated_ip' => false,
                    'port_range' => [],
                ],
                'allocation' => [
                    'default' => null,
                ],
                'node_id' => $nodeId,
            ])
            ->throw();

        return PelicanServer::fromApiResponse($response->json());
    }

    /**
     * Full-control variant of createServer() — accepts the complete Pelican
     * Application API payload (limits, environment, allocations, feature
     * limits, OOM, startup, scripts). Used by the Bridge when provisioning
     * from a Stripe webhook : the legacy createServer() left too many fields
     * hardcoded.
     *
     * @throws RequestException
     */
    public function createServerAdvanced(CreateServerRequest $request): PelicanServer
    {
        $response = $this->http->request()
            ->post('/api/application/servers', $request->toApiPayload())
            ->throw();

        return PelicanServer::fromApiResponse($response->json());
    }

    /**
     * Update a server's resource build (limits, feature_limits, allocation
     * counts, oom). Used by the Bridge when a Stripe subscription.updated
     * event signals an upgrade or downgrade of plan.
     *
     * @param  array<string, mixed>  $build  Pelican PATCH /servers/{id}/build payload
     *
     * @throws RequestException
     */
    public function updateServerBuild(int $pelicanServerId, array $build): PelicanServer
    {
        $response = $this->http->request()
            ->patch("/api/application/servers/{$pelicanServerId}/build", $build)
            ->throw();

        return PelicanServer::fromApiResponse($response->json());
    }

    /**
     * @throws RequestException
     */
    public function suspendServer(int $pelicanServerId): void
    {
        $this->http->request()
            ->post("/api/application/servers/{$pelicanServerId}/suspend")
            ->throw();
    }

    /**
     * @throws RequestException
     */
    public function unsuspendServer(int $pelicanServerId): void
    {
        $this->http->request()
            ->post("/api/application/servers/{$pelicanServerId}/unsuspend")
            ->throw();
    }

    /**
     * @throws RequestException
     */
    public function deleteServer(int $pelicanServerId): void
    {
        $this->http->request()
            ->delete("/api/application/servers/{$pelicanServerId}")
            ->throw();
    }

    /**
     * @throws RequestException
     */
    public function getServer(int $pelicanServerId): PelicanServer
    {
        $response = $this->http->request()
            ->get("/api/application/servers/{$pelicanServerId}")
            ->throw();

        return PelicanServer::fromApiResponse($response->json());
    }

    /**
     * @return PelicanServer[]
     *
     * @throws RequestException
     */
    public function listServers(?int $userId = null): array
    {
        $query = $userId !== null ? ['filter[user]' => $userId] : [];

        return $this->http->fetchAllPages('/api/application/servers', PelicanServer::class, $query);
    }

    // Nodes ---------------------------------------------------------------

    /**
     * @return PelicanNode[]
     *
     * @throws RequestException
     */
    public function listNodes(): array
    {
        return $this->http->fetchAllPages('/api/application/nodes', PelicanNode::class);
    }

    /**
     * @throws RequestException
     */
    public function getNode(int $nodeId): PelicanNode
    {
        $response = $this->http->request()
            ->get("/api/application/nodes/{$nodeId}")
            ->throw();

        return PelicanNode::fromApiResponse($response->json());
    }

    /**
     * @throws RequestException
     */
    public function deleteNode(int $pelicanNodeId): void
    {
        $this->http->request()
            ->delete("/api/application/nodes/{$pelicanNodeId}")
            ->throw();
    }

    /**
     * List all network allocations on a node (paginated). Used by the Bridge
     * PortAllocator to find a contiguous block of free ports.
     *
     * @return PelicanAllocation[]
     *
     * @throws RequestException
     */
    public function listNodeAllocations(int $nodeId): array
    {
        return $this->http->fetchAllPages(
            "/api/application/nodes/{$nodeId}/allocations",
            PelicanAllocation::class,
        );
    }

    // Eggs ----------------------------------------------------------------

    /**
     * Pelican removed the /nests API. Nests are derived from eggs during sync.
     *
     * @return PelicanEgg[]
     *
     * @throws RequestException
     */
    public function listEggs(): array
    {
        return $this->http->fetchAllPages('/api/application/eggs', PelicanEgg::class);
    }

    /**
     * @throws RequestException
     */
    public function getEgg(int $eggId): PelicanEgg
    {
        $response = $this->http->request()
            ->get("/api/application/eggs/{$eggId}")
            ->throw();

        return PelicanEgg::fromApiResponse($response->json());
    }

    /**
     * @throws RequestException
     */
    public function deleteEgg(int $pelicanEggId): void
    {
        $this->http->request()
            ->delete("/api/application/eggs/{$pelicanEggId}")
            ->throw();
    }

    /**
     * Fetch the variable definitions for an egg from Pelican. Returns a
     * map of env_variable name → default_value, suitable for seeding the
     * `environment` payload of createServerAdvanced(). Pelican rejects a
     * server creation when required egg variables are missing — this lookup
     * is mandatory before any provisioning call.
     *
     * Local DB doesn't store these (Egg model has no env_default column),
     * so we hit the Application API at provisioning time.
     *
     * @return array<string, scalar|null>
     *
     * @throws RequestException
     */
    public function getEggVariableDefaults(int $eggId): array
    {
        $response = $this->http->request()
            ->get("/api/application/eggs/{$eggId}", ['include' => 'variables'])
            ->throw();

        $variables = data_get($response->json(), 'attributes.relationships.variables.data', []);
        $defaults = [];

        foreach ($variables as $entry) {
            $attrs = $entry['attributes'] ?? [];
            $key = $attrs['env_variable'] ?? null;
            if ($key !== null && $key !== '') {
                $defaults[(string) $key] = $attrs['default_value'] ?? '';
            }
        }

        return $defaults;
    }
}
