<?php

namespace App\Services\Pelican;

use App\Services\Pelican\DTOs\CreateServerRequest;
use App\Services\Pelican\DTOs\PelicanAllocation;
use App\Services\Pelican\DTOs\PelicanEgg;
use App\Services\Pelican\DTOs\PelicanNode;
use App\Services\Pelican\DTOs\PelicanServer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

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
     * PortAllocator to find a contiguous block of free ports, and by the
     * mirror backfiller to mirror only the allocations attributed to a server
     * (set `$includeServer = true` so the response carries `server_id`).
     *
     * @return PelicanAllocation[]
     *
     * @throws RequestException
     */
    public function listNodeAllocations(int $nodeId, bool $includeServer = false): array
    {
        return $this->http->fetchAllPages(
            "/api/application/nodes/{$nodeId}/allocations",
            PelicanAllocation::class,
            $includeServer ? ['include' => 'server'] : [],
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
        $defaults = [];
        foreach ($this->getEggVariableDefinitions($eggId) as $definition) {
            $defaults[$definition['env_variable']] = $definition['default'];
        }

        return $defaults;
    }

    /**
     * Full egg-variable definitions (default + validation rules) — what the
     * provisioning path needs to normalise values against Pelican's own
     * validation before creating a server.
     *
     * @return list<array{env_variable: string, default: string, rules: string}>
     *
     * @throws RequestException
     */
    public function getEggVariableDefinitions(int $eggId): array
    {
        $response = $this->http->request()
            ->get("/api/application/eggs/{$eggId}", ['include' => 'variables'])
            ->throw();

        $variables = data_get($response->json(), 'attributes.relationships.variables.data', []);
        $definitions = [];

        foreach ($variables as $entry) {
            $attrs = $entry['attributes'] ?? [];
            $key = $attrs['env_variable'] ?? null;
            if ($key === null || $key === '') {
                continue;
            }
            // Pelican has historically served rules as a pipe string; newer
            // builds may return an array — accept both.
            $rules = $attrs['rules'] ?? '';
            $definitions[] = [
                'env_variable' => (string) $key,
                'default' => (string) ($attrs['default_value'] ?? ''),
                'rules' => is_array($rules) ? implode('|', array_map(strval(...), $rules)) : (string) $rules,
            ];
        }

        return $definitions;
    }

    /**
     * Fetch an egg's variable definitions for UI autocomplete (the template
     * editor's "link a parameter to an env var"). Unlike getEggVariableDefaults,
     * this returns a list carrying each variable's friendly name beside its key.
     *
     * @return list<array{env_variable: string, name: string, default: string}>
     *
     * @throws RequestException
     */
    public function getEggVariables(int $eggId): array
    {
        $response = $this->http->request()
            ->get("/api/application/eggs/{$eggId}", ['include' => 'variables'])
            ->throw();

        $variables = data_get($response->json(), 'attributes.relationships.variables.data', []);
        $out = [];

        foreach ($variables as $entry) {
            $attrs = $entry['attributes'] ?? [];
            $key = $attrs['env_variable'] ?? null;
            if ($key === null || $key === '') {
                continue;
            }
            $out[] = [
                'env_variable' => (string) $key,
                'name' => (string) ($attrs['name'] ?? $key),
                'default' => (string) ($attrs['default_value'] ?? ''),
            ];
        }

        return $out;
    }

    // Console quick-fixes (Docker image / Java version) -------------------

    /**
     * Read a server's live container info: the Docker image currently in use,
     * its startup command + environment (needed to PATCH the image without
     * mutating anything else), the egg id, and — when Pelican includes it on
     * the relationship — the egg's docker_images map.
     *
     * @return array{egg: int, image: string, startup: string, environment: array<string, string>, egg_docker_images: array<string, string>}
     *
     * @throws RequestException
     */
    public function getServerContainer(int $pelicanServerId): array
    {
        $response = $this->http->request()
            ->get("/api/application/servers/{$pelicanServerId}", ['include' => 'egg'])
            ->throw();

        $attrs = (array) ($response->json('attributes') ?? []);
        $container = is_array($attrs['container'] ?? null) ? $attrs['container'] : [];

        $environment = [];
        foreach ((array) ($container['environment'] ?? []) as $key => $value) {
            if (is_string($key)) {
                $environment[$key] = is_scalar($value) ? (string) $value : '';
            }
        }

        $eggImages = $response->json('attributes.relationships.egg.attributes.docker_images');

        return [
            'egg' => (int) ($attrs['egg'] ?? 0),
            'image' => (string) ($container['image'] ?? ''),
            'startup' => (string) ($container['startup_command'] ?? ''),
            'environment' => $environment,
            'egg_docker_images' => is_array($eggImages) ? $this->normaliseDockerImages($eggImages) : [],
        ];
    }

    /**
     * Apply a new Docker image to a server WITHOUT touching its egg, startup
     * command or environment. Mirrors the version-changer plugin's strategy:
     * GET the current container, resend egg/startup/environment verbatim,
     * override only `image`, and skip the install scripts. The caller restarts
     * the server afterwards so Wings pulls the new image.
     *
     * @throws RequestException
     */
    public function updateServerStartupImage(int $pelicanServerId, string $newImage): void
    {
        $newImage = trim($newImage);
        if ($newImage === '') {
            throw new \InvalidArgumentException('Refusing to apply an empty Docker image.');
        }

        $container = $this->getServerContainer($pelicanServerId);
        if ($container['egg'] <= 0) {
            throw new \RuntimeException('Could not read the current egg id from Pelican.');
        }

        $this->http->request()
            ->patch("/api/application/servers/{$pelicanServerId}/startup", [
                'egg' => $container['egg'],
                // Fall back to the egg's default startup if Pelican didn't
                // surface an override — keeps the PATCH legal.
                'startup' => $container['startup'] !== '' ? $container['startup'] : '{{SERVER_JARFILE}}',
                'environment' => $container['environment'],
                'image' => $newImage,
                'skip_scripts' => true,
            ])
            ->throw();
    }

    /**
     * Fetch an egg's `docker_images` map (label → image URL) from Pelican.
     * Cached 5 min per egg — the operator rarely edits an egg's image list and
     * the console quick-fix reads it on every modal open.
     *
     * @return array<string, string>
     *
     * @throws RequestException
     */
    public function getEggDockerImages(int $pelicanEggId): array
    {
        if ($pelicanEggId <= 0) {
            return [];
        }

        return Cache::remember(
            "peregrine:egg-docker-images:{$pelicanEggId}",
            now()->addMinutes(5),
            function () use ($pelicanEggId): array {
                $response = $this->http->request()
                    ->get("/api/application/eggs/{$pelicanEggId}")
                    ->throw();

                $images = $response->json('attributes.docker_images');

                return is_array($images) ? $this->normaliseDockerImages($images) : [];
            },
        );
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return array<string, string>
     */
    private function normaliseDockerImages(array $raw): array
    {
        $out = [];
        foreach ($raw as $label => $image) {
            if (is_string($label) && is_string($image) && trim($image) !== '') {
                $out[trim($label)] = trim($image);
            }
        }

        return $out;
    }
}
