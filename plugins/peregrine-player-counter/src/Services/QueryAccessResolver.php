<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use App\Models\Server;
use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Pelican\PelicanClientService;
use App\Services\Pelican\PelicanNetworkService;
use Illuminate\Support\Facades\Cache;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;

/**
 * Makes a server's query port reachable — the one-click "Resolve" flow, never
 * automatic. The sidecar reaches servers over the public IP, so only ALLOCATED
 * ports are queryable; this resolver guarantees the right port is allocated, by
 * strategy:
 *
 *   - 'var'      : redirectable via a startup variable (ARK RCON_PORT, Sons of
 *                  the Forest QUERY_PORT). Allocate a port, point the variable
 *                  at it, restart. Never touches the game port.
 *   - 'adjacent' : query port hard-wired to game_port + offset (Valheim, 7DtD —
 *                  no variable to redirect). Assign the node's free allocation at
 *                  that exact port to the server via the Application API and
 *                  restart — the game port is left untouched.
 *
 * Destructive (restarts the server); the caller gates on a running server.
 */
class QueryAccessResolver
{
    private const NS = PlayerCounterServiceProvider::PLUGIN_ID;

    public function __construct(
        private PelicanClientService $client,
        private PelicanNetworkService $network,
        private PelicanApplicationService $app,
        private EggGameTypeResolver $resolver,
        private QueryPortStrategy $strategy,
        private ServerPlayerCountService $players,
    ) {}

    /**
     * @return array{ok: bool, message?: string, error?: string, port?: int, variable?: string, kind?: string}
     */
    public function resolve(Server $server): array
    {
        if (! $server->identifier) {
            return ['ok' => false, 'error' => 'no_identifier'];
        }

        // Defence-in-depth: only act on a fully-running server (a restart of a
        // stopped/installing server is wrong). Mirrors the ARK flow.
        try {
            if ($this->client->getServerResources($server->identifier)->state !== 'running') {
                return ['ok' => false, 'error' => 'not_running'];
            }
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'read_state_failed'];
        }

        $target = $this->resolver->resolve($server->egg);
        if (! $target['queryable'] || ! is_string($target['type'])) {
            return ['ok' => false, 'error' => 'not_queryable'];
        }

        try {
            $context = $this->context($server);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'read_context_failed'];
        }

        $isRcon = in_array($target['type'], (array) config(self::NS.'.rcon.types', []), true);
        $plan = $this->strategy->plan(
            $target['query_port'],
            $isRcon,
            $context['primary'],
            array_values($context['ports']),
            $context['vars'],
        );

        if ($plan['reachable']) {
            return ['ok' => true, 'message' => 'already_reachable', 'port' => $plan['send_port'], 'kind' => $plan['kind']];
        }

        return match ($plan['kind']) {
            'var' => $this->resolveVar($server, (string) ($plan['env'] ?? ''), $context),
            'adjacent' => $this->resolveAdjacent($server, (int) ($plan['offset'] ?? 1), $context),
            default => ['ok' => false, 'error' => 'manual_required', 'kind' => $plan['kind']],
        };
    }

    /**
     * Allocate a fresh port and point the query/RCON startup variable at it.
     *
     * @param  array{primary: array{ip: string, port: int}, ports: array<int, int>, vars: array<string, string>}  $context
     * @return array{ok: bool, message?: string, error?: string, port?: int, variable?: string, kind?: string}
     */
    private function resolveVar(Server $server, string $variable, array $context): array
    {
        if ($variable === '') {
            return ['ok' => false, 'error' => 'no_target_variable'];
        }

        $allocation = $this->addAllocation($server);
        if (! $allocation) {
            return ['ok' => false, 'error' => 'no_free_allocation'];
        }

        try {
            $this->client->updateStartupVariable($server->identifier, $variable, (string) $allocation['port']);
            $this->client->setPowerState($server->identifier, 'restart');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'apply_failed'];
        }

        $this->players->invalidate($server);

        return ['ok' => true, 'message' => 'query_resolved', 'port' => $allocation['port'], 'variable' => $variable, 'kind' => 'var'];
    }

    /**
     * Make the hard-wired query port (game_port + offset — e.g. Valheim's
     * game+1) reachable WITHOUT touching the game port: find the node's free
     * allocation at that exact port and assign it to the server via the
     * Application API (which can target a specific port; the client API can't),
     * then restart. No startup variable needed — fixes games like Valheim that
     * expose none.
     *
     * @param  array{primary: array{ip: string, port: int}, ports: array<int, int>, vars: array<string, string>}  $context
     * @return array{ok: bool, message?: string, error?: string, port?: int, kind?: string}
     */
    private function resolveAdjacent(Server $server, int $offset, array $context): array
    {
        $pelicanId = $server->pelican_server_id;
        if ($pelicanId === null) {
            return ['ok' => false, 'error' => 'no_identifier'];
        }

        $targetPort = $context['primary']['port'] + $offset;
        if ($targetPort < 1 || $targetPort > 65535) {
            return ['ok' => false, 'error' => 'manual_required'];
        }

        try {
            $pserver = $this->app->getServer($pelicanId);
            $nodeAllocations = $this->app->listNodeAllocations($pserver->nodeId, true);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'read_context_failed'];
        }

        $ip = $context['primary']['ip'];
        $match = null;
        foreach ($nodeAllocations as $a) {
            if ($a->port === $targetPort && ($a->ip === $ip || $a->ipAlias === $ip)) {
                $match = $a;
                break;
            }
        }

        if ($match === null) {
            // The node pool has no allocation at that port — only a node admin
            // can add one (the client/build API can't create arbitrary ports).
            return ['ok' => false, 'error' => 'query_port_unavailable', 'port' => $targetPort];
        }
        if ($match->assigned && $match->serverId !== $pelicanId) {
            return ['ok' => false, 'error' => 'query_port_taken', 'port' => $targetPort];
        }

        try {
            if (! $match->assigned) {
                $this->app->updateServerBuild($pelicanId, $this->buildAddingAllocation($server, $pserver, $match->id, count($context['ports'])));
            }
            $this->client->setPowerState($server->identifier, 'restart');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'apply_failed'];
        }

        Cache::forget("server_network:{$server->identifier}");
        Cache::forget("server_allocation:{$server->identifier}");
        $this->players->invalidate($server);

        return ['ok' => true, 'message' => 'query_resolved', 'port' => $targetPort, 'kind' => 'adjacent'];
    }

    /**
     * Build PATCH payload that adds one allocation, preserving the current limits
     * and lifting the allocation feature-limit so Pelican accepts the extra port.
     *
     * @return array<string, mixed>
     */
    private function buildAddingAllocation(Server $server, PelicanServer $pserver, int $allocationId, int $currentAllocations): array
    {
        $features = (array) ($this->client->getRawServer($server->identifier)['feature_limits'] ?? []);
        $limits = $pserver->limits;

        return [
            'allocation' => $pserver->defaultAllocationId,
            'memory' => $limits->memory,
            'swap' => $limits->swap,
            'disk' => $limits->disk,
            'io' => $limits->io,
            'cpu' => $limits->cpu,
            'feature_limits' => [
                'databases' => (int) ($features['databases'] ?? 0),
                'backups' => (int) ($features['backups'] ?? 0),
                'allocations' => max((int) ($features['allocations'] ?? 1), $currentAllocations + 1),
            ],
            'add_allocations' => [$allocationId],
        ];
    }

    /**
     * Read the server's primary allocation, full port set (id => port) and
     * startup variables (name => value) in one place.
     *
     * @return array{primary: array{ip: string, port: int}, ports: array<int, int>, vars: array<string, string>}
     */
    private function context(Server $server): array
    {
        $allocations = $this->network->listAllocations($server->identifier);
        $ports = [];
        $primary = null;
        foreach ($allocations as $alloc) {
            $a = $alloc['attributes'] ?? $alloc;
            $id = (int) ($a['id'] ?? 0);
            $port = (int) ($a['port'] ?? 0);
            if ($id > 0 && $port > 0) {
                $ports[$id] = $port;
            }
            $primary ??= $a;
            if ($a['is_default'] ?? false) {
                $primary = $a;
            }
        }

        $vars = [];
        foreach ($this->client->getStartupVariables($server->identifier) as $v) {
            $key = $v['env_variable'] ?? null;
            if (is_string($key)) {
                $value = $v['server_value'] ?? '';
                $vars[$key] = (string) (($value === '' || $value === null) ? ($v['default_value'] ?? '') : $value);
            }
        }

        return [
            'primary' => [
                'ip' => (string) ($primary['ip_alias'] ?? $primary['ip'] ?? ''),
                'port' => (int) ($primary['port'] ?? 0),
            ],
            'ports' => $ports,
            'vars' => $vars,
        ];
    }

    /**
     * @return array{id: int, port: int}|null
     */
    private function addAllocation(Server $server): ?array
    {
        try {
            $raw = $this->network->addAllocation($server->identifier);
        } catch (\Throwable) {
            return null;
        }

        Cache::forget("server_network:{$server->identifier}");
        Cache::forget("server_allocation:{$server->identifier}");

        $a = $raw['attributes'] ?? $raw;
        $id = (int) ($a['id'] ?? 0);
        $port = (int) ($a['port'] ?? 0);

        return ($id > 0 && $port >= 1 && $port <= 65535) ? ['id' => $id, 'port' => $port] : null;
    }
}
