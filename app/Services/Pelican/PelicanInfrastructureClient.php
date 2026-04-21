<?php

namespace App\Services\Pelican;

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
}
