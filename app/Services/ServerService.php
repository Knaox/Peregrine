<?php

namespace App\Services;

use App\Events\Mirror\ServerMirrorChanged;
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
        $this->broadcastMirrorChange();
    }

    /**
     * @throws RequestException
     */
    public function unsuspend(): void
    {
        $this->applicationService->unsuspendServer($this->server->pelican_server_id);
        $this->server->update(['status' => 'active']);
        $this->broadcastMirrorChange();
    }

    /**
     * Push the local row's new status onto Reverb so the dashboard /
     * detail page / admin mirror flip the suspended badge + sidebar
     * gates within ~100 ms instead of waiting on the 5-min React
     * Query staleTime. Identical shape to
     * `BroadcastsServerMirror::broadcastServerMirrorChanged()` ; we
     * inline it here because this service isn't a Job (where the
     * trait lives) and pulling Eloquent / Broadcast at the trait
     * layer would force every Service consumer to wire the dispatcher.
     *
     * Best-effort : a Reverb outage or misconfiguration MUST NOT
     * regress the underlying suspend/unsuspend mutation, so any throw
     * is swallowed — the next 5-min cron sweep (SyncServerStatusJob's
     * runtime sync) will eventually surface the change.
     */
    private function broadcastMirrorChange(): void
    {
        try {
            event(new ServerMirrorChanged(
                serverId: (int) $this->server->id,
                resource: ServerMirrorChanged::RESOURCE_SERVER,
                action: ServerMirrorChanged::ACTION_UPSERT,
                resourceId: (int) $this->server->id,
                accessUserIds: $this->server->accessUsers()->pluck('users.id')->all(),
            ));
        } catch (\Throwable) {
            // intentionally silent — see method-level comment
        }
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
