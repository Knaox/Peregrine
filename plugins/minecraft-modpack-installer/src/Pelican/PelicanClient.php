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
     * Returns the decoded response body so callers can confirm Pelican
     * accepted the swap (in particular, that `attributes.egg` matches
     * the requested egg id) before kicking off a reinstall. If Pelican
     * returns an empty 204, callers should fall back to `getServerRaw()`.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateServerStartup(int $pelicanServerId, array $payload): array
    {
        $response = $this->http->request()
            ->patch("/api/application/servers/{$pelicanServerId}/startup", $payload)
            ->throw();

        $body = $response->json();

        return is_array($body) ? $body : [];
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
     * Reset every BB_MODPACK_* env var on the server to a harmless default
     * value while we're still on the installer egg.
     *
     * Pelican's `StartupModificationService` (the service behind
     * `PATCH /api/application/servers/{id}/startup`) calls
     * `ServerVariable::updateOrCreate(...)` per env key but never deletes
     * orphans (verified against the Pelican source — confirmed by
     * pelican-dev/panel discussion #430 and PR #811 which added a
     * deletion-based `EggChangerService` that is *only* invoked from the
     * Filament admin "Change egg" form, not from the API). The Application
     * API does not expose any flag to bypass that limitation.
     *
     * Wings itself filters by current egg via the `Server::variables()`
     * relationship (`->hasMany(EggVariable::class, 'egg_id', 'egg_id')`)
     * so the BB_MODPACK_* orphans never reach the runtime container —
     * but they do surface in admin UIs and panel debug tools. This scrub
     * neutralises the values so the rows that remain in Pelican's DB
     * carry no modpack-specific data.
     *
     * Values here must satisfy the installer egg's validation rules; we use
     * the rules' permissive minimums (provider → modrinth, ids → '_', etc.).
     */
    public function scrubInstallerEnvironment(int $pelicanServerId, int $installerEggId, string $startupCommand): void
    {
        $this->http->request()
            ->patch("/api/application/servers/{$pelicanServerId}/startup", [
                'egg' => $installerEggId,
                'image' => 'ghcr.io/pelican-eggs/yolks:java_21',
                'startup' => $startupCommand,
                'environment' => [
                    'BB_MODPACK_PROVIDER' => 'modrinth',
                    'BB_MODPACK_ID' => '_',
                    'BB_MODPACK_VERSION_ID' => '_',
                    'BB_MODPACK_GAME_VERSION' => '',
                    'BB_MODPACK_PURGE' => '0',
                    'BB_MODPACK_CURSEFORGE_KEY' => '',
                    'BB_MODPACK_OPERATION' => 'install',
                    'SERVER_JARFILE' => 'server.jar',
                ],
                'skip_scripts' => true,
            ])
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
