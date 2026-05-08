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
    public function scrubInstallerEnvironment(
        int $pelicanServerId,
        int $installerEggId,
        string $startupCommand,
        string $image,
    ): void {
        $this->http->request()
            ->patch("/api/application/servers/{$pelicanServerId}/startup", [
                'egg' => $installerEggId,
                // Caller picks the image (resolved via JavaCompatibilityMatrix
                // → never hardcoded). Scrubbing only mutates env vars and
                // skip_scripts — the image is included so Pelican's PATCH
                // doesn't surprise us by zeroing the field, not because the
                // scrub itself depends on the runtime.
                'image' => $image,
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
                // skip_scripts MUST be false: PATCH /startup never triggers
                // an install on its own, so a true value provides no
                // benefit, but it's stored persistently on the server row
                // and would silently skip every future native /reinstall.
                'skip_scripts' => false,
            ])
            ->throw();
    }

    /**
     * Import an egg into Pelican via the Application API.
     *
     * Important: contrary to older comments, Pelican's
     * `POST /api/application/eggs/import` does NOT upsert by UUID —
     * re-POSTing the same UUID raises HTTP 500 (`UniqueConstraintViolationException`)
     * because the importer service blindly calls `Egg::create()`. Callers
     * MUST therefore look up an existing egg via {@see findEggIdByUuid()}
     * before invoking this method.
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

    /**
     * Look up a Pelican egg by its UUID. Walks paginated `GET /eggs` until
     * a match is found or the list is exhausted. Returns `null` when no
     * egg has the requested UUID.
     *
     * Used by {@see \Plugins\MinecraftModpackInstaller\Services\EggImporter}
     * before attempting a (re)import — Pelican's import endpoint 500s on
     * UUID collisions, so we have to do the existence check ourselves.
     */
    /**
     * Return the env_variable names declared on a given egg. Used by the
     * diagnostic tooling to detect when Pelican's egg has drifted from the
     * bundled template (e.g. after a half-applied import that left the
     * variable rows in an inconsistent state).
     *
     * @return list<string>
     */
    public function getEggVariableEnvNames(int $eggId): array
    {
        try {
            $response = $this->http->request()
                ->get("/api/application/eggs/{$eggId}", ['include' => 'variables'])
                ->throw()
                ->json();
        } catch (\Throwable) {
            return [];
        }

        $rows = $response['attributes']['relationships']['variables']['data']
            ?? $response['data']['attributes']['relationships']['variables']['data']
            ?? [];

        if (! is_array($rows)) {
            return [];
        }

        $names = [];
        foreach ($rows as $row) {
            $env = $row['attributes']['env_variable'] ?? null;
            if (is_string($env) && $env !== '') {
                $names[] = $env;
            }
        }

        return $names;
    }

    /**
     * Hard-delete an egg from Pelican via the Application API. Used by the
     * `modpacks:import-egg --hard` console command to recover from a
     * corrupted egg row (e.g. variables out of sync with the bundled
     * template). Returns silently on 404 (egg already gone).
     */
    public function deleteEgg(int $eggId): void
    {
        $response = $this->http->request()
            ->delete("/api/application/eggs/{$eggId}");

        // 404 = already gone, treat as success. Anything else (including
        // 409 if a server still references the egg) bubbles up.
        if ($response->status() === 404) {
            return;
        }

        $response->throw();
    }

    public function findEggIdByUuid(string $uuid): ?int
    {
        $page = 1;
        $maxPages = 20; // safety cap; even huge installs ship < 200 eggs
        while ($page <= $maxPages) {
            try {
                $response = $this->http->request()
                    ->get('/api/application/eggs', ['per_page' => 100, 'page' => $page])
                    ->throw()
                    ->json();
            } catch (\Throwable) {
                return null;
            }

            $rows = $response['data'] ?? [];
            if (! is_array($rows) || $rows === []) {
                return null;
            }

            foreach ($rows as $row) {
                $attrs = $row['attributes'] ?? [];
                if (! is_array($attrs)) {
                    continue;
                }
                if (($attrs['uuid'] ?? null) === $uuid) {
                    $id = $attrs['id'] ?? null;
                    if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                        return (int) $id;
                    }
                }
            }

            $pagination = $response['meta']['pagination'] ?? null;
            $totalPages = is_array($pagination) ? (int) ($pagination['total_pages'] ?? 1) : 1;
            if ($page >= $totalPages) {
                return null;
            }
            $page++;
        }

        return null;
    }
}
