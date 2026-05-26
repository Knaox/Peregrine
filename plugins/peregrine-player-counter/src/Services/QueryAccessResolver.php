<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use App\Services\Pelican\PelicanNetworkService;
use Illuminate\Support\Facades\Cache;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;

/**
 * Makes a server's query port reachable with zero admin/player action — the
 * one-click ARK flow, generalised and run automatically. The sidecar reaches
 * servers over the public IP, so only ALLOCATED ports are queryable; this
 * resolver guarantees the right port is allocated, by strategy:
 *
 *   - 'var'      : redirectable via a startup variable (ARK RCON_PORT, Sons of
 *                  the Forest QueryPort). Allocate a port, point the variable
 *                  at it, restart. Never touches the game port — safe anytime.
 *   - 'adjacent' : query port hard-wired to game_port + offset (Valheim, 7DtD).
 *                  Acquire an adjacent allocation pair, move the GAME port onto
 *                  the lower one (so game+offset is allocated too) and restart.
 *                  Surplus allocations added while hunting are released so we
 *                  never accumulate dead ports.
 *
 * Destructive (restarts the server); the caller gates on a running server and
 * an attempt marker so an unresolvable game can't trigger a restart loop.
 */
class QueryAccessResolver
{
    private const NS = PlayerCounterServiceProvider::PLUGIN_ID;

    public function __construct(
        private PelicanClientService $client,
        private PelicanNetworkService $network,
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
     * Acquire an adjacent allocation pair (P, P+offset), move the game port onto
     * P (so P+offset — the hard-wired query port — is allocated too), make P
     * primary and restart. Releases any surplus ports added while hunting.
     *
     * @param  array{primary: array{ip: string, port: int}, ports: array<int, int>, vars: array<string, string>}  $context
     * @return array{ok: bool, message?: string, error?: string, port?: int, variable?: string, kind?: string}
     */
    private function resolveAdjacent(Server $server, int $offset, array $context): array
    {
        $gamePortVar = $this->firstPresent($context['vars'], (array) config(self::NS.'.auto_resolve.game_port_vars', []));
        if ($gamePortVar === null) {
            return ['ok' => false, 'error' => 'no_game_port_variable'];
        }

        $ports = $context['ports']; // id => port
        $added = [];
        $cap = (int) config(self::NS.'.auto_resolve.max_alloc_attempts', 12);

        // Add allocations until the set contains an adjacent pair (P, P+offset).
        for ($i = 0; $i <= $cap; $i++) {
            $pair = $this->findAdjacentPair($ports, $offset);
            if ($pair !== null) {
                $surplus = array_diff_key($added, [$pair['low_id'] => true, $pair['high_id'] => true]);
                $this->release($server, array_keys($surplus));

                return $this->applyAdjacent($server, $gamePortVar, $pair);
            }
            if ($i === $cap) {
                break;
            }
            $allocation = $this->addAllocation($server);
            if (! $allocation) {
                break;
            }
            $ports[$allocation['id']] = $allocation['port'];
            $added[$allocation['id']] = $allocation['port'];
        }

        $this->release($server, array_keys($added));

        return ['ok' => false, 'error' => 'no_adjacent_pair'];
    }

    /**
     * @param  array{low_id: int, low_port: int, high_id: int}  $pair
     * @return array{ok: bool, message?: string, error?: string, port?: int, variable?: string, kind?: string}
     */
    private function applyAdjacent(Server $server, string $gamePortVar, array $pair): array
    {
        try {
            $this->client->updateStartupVariable($server->identifier, $gamePortVar, (string) $pair['low_port']);
            $this->network->setPrimaryAllocation($server->identifier, $pair['low_id']);
            $this->client->setPowerState($server->identifier, 'restart');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'apply_failed'];
        }

        Cache::forget("server_allocation:{$server->identifier}");
        $this->players->invalidate($server);

        return ['ok' => true, 'message' => 'query_resolved', 'port' => $pair['low_port'], 'variable' => $gamePortVar, 'kind' => 'adjacent'];
    }

    /**
     * @param  array<int, int>  $ports  id => port
     * @return array{low_id: int, low_port: int, high_id: int}|null
     */
    private function findAdjacentPair(array $ports, int $offset): ?array
    {
        foreach ($ports as $lowId => $lowPort) {
            $highPort = $lowPort + $offset;
            $highId = array_search($highPort, $ports, true);
            if ($highId !== false && $highId !== $lowId) {
                return ['low_id' => (int) $lowId, 'low_port' => $lowPort, 'high_id' => (int) $highId];
            }
        }

        return null;
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

    /**
     * @param  list<int>  $allocationIds
     */
    private function release(Server $server, array $allocationIds): void
    {
        foreach ($allocationIds as $id) {
            try {
                $this->network->deleteAllocation($server->identifier, (int) $id);
            } catch (\Throwable) {
                // Best-effort cleanup — a leftover allocation isn't fatal.
            }
        }
    }

    /**
     * @param  array<string, string>  $vars
     * @param  list<string>  $candidates
     */
    private function firstPresent(array $vars, array $candidates): ?string
    {
        foreach ($candidates as $name) {
            if (array_key_exists((string) $name, $vars)) {
                return (string) $name;
            }
        }

        return null;
    }
}
