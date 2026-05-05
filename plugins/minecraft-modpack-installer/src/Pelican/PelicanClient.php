<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Pelican;

use App\Services\Pelican\PelicanHttpClient;
use RuntimeException;

/**
 * Plugin-local wrapper around the core PelicanHttpClient — exposes the
 * three Application API endpoints the modpack flow needs that the core
 * PelicanInfrastructureClient does not currently expose:
 *
 *  - GET    /api/application/servers/{id}             (raw, for snapshotting)
 *  - PATCH  /api/application/servers/{id}/startup    (egg-swap path)
 *  - POST   /api/application/servers/{id}/reinstall  (kick the install pipeline)
 *  - POST   /api/application/eggs/import              (one-shot egg upload)
 *
 * Lives in the plugin so the core stays untouched. Reuses the core HTTP
 * client (auth, retries, base URL).
 */
class PelicanClient
{
    public function __construct(private readonly PelicanHttpClient $http) {}

    /**
     * Raw server fetch for fields the core DTO doesn't expose.
     * `attributes.container.{image,startup_command,environment}` and
     * `attributes.egg` are read by the install job to snapshot the server's
     * current state before swapping the egg.
     *
     * @param  list<string>  $include  Pelican `include` tokens (e.g. `variables`).
     * @return array<string, mixed>
     */
    public function getServerRaw(int $pelicanServerId, array $include = []): array
    {
        $query = $include === [] ? [] : ['include' => implode(',', $include)];

        return $this->http->request()
            ->get("/api/application/servers/{$pelicanServerId}", $query)
            ->throw()
            ->json();
    }

    /**
     * Update the startup configuration of a server: egg, docker image,
     * startup string, environment variables, skip_scripts. Pelican's
     * StartupModificationService validates `environment` against the new
     * egg's variable rules — caller must pass at least the required vars.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateServerStartup(int $pelicanServerId, array $payload): void
    {
        $this->http->request()
            ->patch("/api/application/servers/{$pelicanServerId}/startup", $payload)
            ->throw();
    }

    /**
     * Trigger a server reinstall on Wings. Pelican-side status flips to
     * `installing` until Wings POSTs the install completion callback.
     */
    public function reinstallServer(int $pelicanServerId): void
    {
        $this->http->request()
            ->post("/api/application/servers/{$pelicanServerId}/reinstall")
            ->throw();
    }

    /**
     * Import an egg into Pelican via the Application API. UUID match in
     * the payload triggers an in-place update of the existing egg row.
     *
     * @param  array<string, mixed>  $payload  Decoded egg JSON (PLCN_v3 document).
     * @return int Pelican egg ID.
     */
    public function importEgg(array $payload): int
    {
        $response = $this->http->request()
            ->post('/api/application/eggs/import', $payload)
            ->throw()
            ->json();

        $id = $response['attributes']['id']
            ?? $response['data']['attributes']['id']
            ?? null;

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            throw new RuntimeException('Pelican egg import: response missing attributes.id');
        }

        return (int) $id;
    }
}
