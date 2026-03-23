<?php

namespace App\Services\Pelican;

use App\Services\Pelican\Concerns\MakesClientRequests;
use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\DTOs\ServerResources;
use App\Services\Pelican\DTOs\WebsocketCredentials;

class PelicanClientService
{
    use MakesClientRequests;
    // -------------------------------------------------------------------------
    // Servers
    // -------------------------------------------------------------------------

    /**
     * List all servers accessible by the client API key.
     *
     * @return PelicanServer[]
     *
     * @throws RequestException
     */
    public function listServers(): array
    {
        $items = [];
        $page = 1;

        do {
            $response = $this->request()
                ->get('/api/client', ['page' => $page])
                ->throw();

            $json = $response->json();
            $data = $json['data'] ?? [];

            foreach ($data as $item) {
                $items[] = PelicanServer::fromApiResponse($item);
            }

            $totalPages = $json['meta']['pagination']['total_pages'] ?? 1;
            $page++;
        } while ($page <= $totalPages);

        return $items;
    }

    /**
     * Get a single server by its identifier.
     *
     * @throws RequestException
     */
    public function getServer(string $serverIdentifier): PelicanServer
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}")
            ->throw();

        return PelicanServer::fromApiResponse($response->json());
    }

    /**
     * Get raw server data from Pelican Client API (unstructured array).
     *
     * @return array<string, mixed>
     */
    public function getRawServer(string $serverIdentifier): array
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}")
            ->throw();

        return $response->json('attributes') ?? $response->json() ?? [];
    }

    /**
     * Get current resource usage for a server.
     *
     * @throws RequestException
     */
    public function getServerResources(string $serverIdentifier): ServerResources
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/resources")
            ->throw();

        return ServerResources::fromApiResponse($response->json());
    }

    // -------------------------------------------------------------------------
    // Console & power
    // -------------------------------------------------------------------------

    /**
     * Send a command to the server console.
     *
     * @throws RequestException
     */
    public function sendCommand(string $serverIdentifier, string $command): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/command", [
                'command' => $command,
            ])
            ->throw();
    }

    /**
     * Set the power state of a server (start, stop, restart, kill).
     *
     * @throws RequestException
     */
    public function setPowerState(string $serverIdentifier, string $signal): void
    {
        $this->request()
            ->post("/api/client/servers/{$serverIdentifier}/power", [
                'signal' => $signal,
            ])
            ->throw();
    }

    // -------------------------------------------------------------------------
    // Startup variables
    // -------------------------------------------------------------------------

    /**
     * Get startup variables for a server.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function getStartupVariables(string $serverIdentifier): array
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/startup")
            ->throw();

        $data = $response->json('data') ?? [];

        return array_map(fn (array $item) => $item['attributes'] ?? $item, $data);
    }

    /**
     * Update a single startup variable.
     *
     * @throws RequestException
     */
    public function updateStartupVariable(string $serverIdentifier, string $key, string $value): void
    {
        $this->request()
            ->put("/api/client/servers/{$serverIdentifier}/startup/variable", [
                'key' => $key,
                'value' => $value,
            ])
            ->throw();
    }

    // -------------------------------------------------------------------------
    // Websocket
    // -------------------------------------------------------------------------

    /**
     * Get websocket credentials for a server.
     *
     * @throws RequestException
     */
    public function getWebsocket(string $serverIdentifier): WebsocketCredentials
    {
        $response = $this->request()
            ->get("/api/client/servers/{$serverIdentifier}/websocket")
            ->throw();

        return WebsocketCredentials::fromApiResponse($response->json());
    }

}
