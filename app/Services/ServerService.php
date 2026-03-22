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
     * @throws RequestException
     */
    public function start(): void
    {
        $this->clientService->setPowerState($this->serverIdentifier(), 'start');
    }

    /**
     * @throws RequestException
     */
    public function stop(): void
    {
        $this->clientService->setPowerState($this->serverIdentifier(), 'stop');
    }

    /**
     * @throws RequestException
     */
    public function restart(): void
    {
        $this->clientService->setPowerState($this->serverIdentifier(), 'restart');
    }

    /**
     * @throws RequestException
     */
    public function kill(): void
    {
        $this->clientService->setPowerState($this->serverIdentifier(), 'kill');
    }

    // -------------------------------------------------------------------------
    // Suspension (Application API)
    // -------------------------------------------------------------------------

    /**
     * @throws RequestException
     */
    public function suspend(): void
    {
        $this->applicationService->suspendServer($this->server->pelican_server_id);
        $this->server->update(['status' => 'suspended']);
    }

    /**
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
     * @throws RequestException
     */
    public function getResources(): ServerResources
    {
        return $this->clientService->getServerResources($this->serverIdentifier());
    }

    /**
     * @throws RequestException
     */
    public function getWebsocketCredentials(): WebsocketCredentials
    {
        return $this->clientService->getWebsocket($this->serverIdentifier());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function serverIdentifier(): string
    {
        if (!empty($this->server->identifier)) {
            return $this->server->identifier;
        }

        $pelicanServer = $this->applicationService->getServer($this->server->pelican_server_id);

        return $pelicanServer->identifier;
    }
}
