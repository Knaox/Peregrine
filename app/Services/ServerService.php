<?php

namespace App\Services;

use App\Models\Server;
use App\Services\Pelican\DTOs\ServerResources;
use App\Services\Pelican\DTOs\WebsocketCredentials;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Pelican\PelicanClientService;
use Illuminate\Http\Client\RequestException;

class ServerService
{
    public function __construct(
        private Server $server,
        private PelicanClientService $clientService,
        private PelicanApplicationService $applicationService,
    ) {}

    // -------------------------------------------------------------------------
    // Power management (Client API)
    // -------------------------------------------------------------------------

    /**
     * Start the server.
     *
     * @throws RequestException
     */
    public function start(string $clientApiKey): void
    {
        $this->clientService->setPowerState(
            $clientApiKey,
            $this->serverIdentifier(),
            'start',
        );
    }

    /**
     * Stop the server gracefully.
     *
     * @throws RequestException
     */
    public function stop(string $clientApiKey): void
    {
        $this->clientService->setPowerState(
            $clientApiKey,
            $this->serverIdentifier(),
            'stop',
        );
    }

    /**
     * Restart the server.
     *
     * @throws RequestException
     */
    public function restart(string $clientApiKey): void
    {
        $this->clientService->setPowerState(
            $clientApiKey,
            $this->serverIdentifier(),
            'restart',
        );
    }

    /**
     * Kill the server process immediately.
     *
     * @throws RequestException
     */
    public function kill(string $clientApiKey): void
    {
        $this->clientService->setPowerState(
            $clientApiKey,
            $this->serverIdentifier(),
            'kill',
        );
    }

    // -------------------------------------------------------------------------
    // Suspension (Application API)
    // -------------------------------------------------------------------------

    /**
     * Suspend the server via the Application API.
     *
     * @throws RequestException
     */
    public function suspend(): void
    {
        $this->applicationService->suspendServer($this->server->pelican_server_id);

        $this->server->update(['status' => 'suspended']);
    }

    /**
     * Unsuspend the server via the Application API.
     *
     * @throws RequestException
     */
    public function unsuspend(): void
    {
        $this->applicationService->unsuspendServer($this->server->pelican_server_id);

        $this->server->update(['status' => 'active']);
    }

    // -------------------------------------------------------------------------
    // Deletion (Application API + local DB)
    // -------------------------------------------------------------------------

    /**
     * Delete the server from Pelican and the local database.
     *
     * @throws RequestException
     */
    public function delete(): void
    {
        $this->applicationService->deleteServer($this->server->pelican_server_id);

        $this->server->delete();
    }

    // -------------------------------------------------------------------------
    // Resources & Websocket (Client API)
    // -------------------------------------------------------------------------

    /**
     * Get the current resource usage for this server.
     *
     * @throws RequestException
     */
    public function getResources(string $clientApiKey): ServerResources
    {
        return $this->clientService->getServerResources(
            $clientApiKey,
            $this->serverIdentifier(),
        );
    }

    /**
     * Get websocket credentials for this server.
     *
     * @throws RequestException
     */
    public function getWebsocketCredentials(string $clientApiKey): WebsocketCredentials
    {
        return $this->clientService->getWebsocket(
            $clientApiKey,
            $this->serverIdentifier(),
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the server identifier used by the Client API.
     *
     * The Client API uses the short identifier (UUID prefix), not the numeric ID.
     * If the local model stores the identifier we use it; otherwise we fall back
     * to fetching the server from the Application API.
     */
    private function serverIdentifier(): string
    {
        if (! empty($this->server->identifier)) {
            return $this->server->identifier;
        }

        $pelicanServer = $this->applicationService->getServer($this->server->pelican_server_id);

        return $pelicanServer->identifier;
    }
}
