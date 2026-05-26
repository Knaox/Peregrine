<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use App\Models\Server;
use App\Services\Pelican\DTOs\PelicanAllocation;
use App\Services\Pelican\DTOs\PelicanServer;
use App\Services\Pelican\PelicanApplicationService;
use App\Services\Pelican\PelicanClientService;
use App\Services\Pelican\PelicanNetworkService;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the "adjacent" query port — games whose query port is hard-wired to
 * game_port + offset with no startup variable to redirect (Valheim, 7DtD, The
 * Forest). The sidecar reaches servers over the public IP, so that exact port
 * must be a published allocation. Two strategies, tried in order:
 *
 *   ADD    — the node already has a FREE allocation at game_port+offset: assign
 *            it to the server. The game port is untouched.
 *   CHANGE — otherwise, relocate the game onto a free CONSECUTIVE pair (P, P+offset)
 *            by assigning both and making P the primary allocation. Wings injects
 *            SERVER_PORT from the primary, so the game binds P (and P+offset) even
 *            without a game-port egg variable. The game port changes.
 *
 * The Application API is required (the client API can't target a specific port).
 * Destructive (restarts the server); the caller gates on a running server.
 */
class AdjacentPortResolver
{
    public function __construct(
        private PelicanApplicationService $app,
        private PelicanNetworkService $network,
        private PelicanClientService $client,
        private ServerPlayerCountService $players,
    ) {}

    /**
     * @param  array{primary: array{ip: string, port: int}, ports: array<int, int>, vars: array<string, string>}  $context
     * @return array{ok: bool, message?: string, error?: string, port?: int, kind?: string}
     */
    public function resolve(Server $server, int $offset, array $context): array
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
            $allocations = $this->app->listNodeAllocations($pserver->nodeId, true);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'read_context_failed'];
        }

        $ip = $context['primary']['ip'];
        $used = count($context['ports']);

        // ADD: the exact game_port+offset is already a free allocation.
        $free = $this->freeAt($allocations, $ip, $targetPort);
        if ($free !== null) {
            return $this->apply($server, $pelicanId, $pserver, [$free->id], null, $used, $targetPort);
        }

        // Taken by THIS server already → reachable (nothing to do).
        if ($this->assignedToSelf($allocations, $ip, $targetPort, $pelicanId)) {
            return ['ok' => true, 'message' => 'already_reachable', 'port' => $targetPort, 'kind' => 'adjacent'];
        }

        // CHANGE: relocate the game onto a free consecutive pair.
        $pair = $this->freePair($allocations, $ip, $offset);
        if ($pair === null) {
            return ['ok' => false, 'error' => 'no_adjacent_ports', 'port' => $targetPort];
        }

        return $this->apply($server, $pelicanId, $pserver, [$pair[0]->id, $pair[1]->id], $pair[0]->id, $used, $pair[0]->port + $offset);
    }

    /**
     * Assign the given node allocations to the server (optionally making one
     * primary), then restart. Returns the resulting query port.
     *
     * @param  list<int>  $allocationIds
     * @return array{ok: bool, message?: string, error?: string, port?: int, kind?: string}
     */
    private function apply(Server $server, int $pelicanId, PelicanServer $pserver, array $allocationIds, ?int $primaryId, int $used, int $queryPort): array
    {
        try {
            $this->app->updateServerBuild($pelicanId, $this->build($server, $pserver, $allocationIds, $used));
            if ($primaryId !== null) {
                $this->network->setPrimaryAllocation($server->identifier, $primaryId);
            }
            $this->client->setPowerState($server->identifier, 'restart');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'apply_failed'];
        }

        Cache::forget("server_network:{$server->identifier}");
        Cache::forget("server_allocation:{$server->identifier}");
        $this->players->invalidate($server);

        return ['ok' => true, 'message' => 'query_resolved', 'port' => $queryPort, 'kind' => 'adjacent'];
    }

    /**
     * Build PATCH payload adding the allocations, preserving limits and lifting
     * the allocation feature-limit so Pelican accepts the extra ports.
     *
     * @param  list<int>  $allocationIds
     * @return array<string, mixed>
     */
    private function build(Server $server, PelicanServer $pserver, array $allocationIds, int $used): array
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
                'allocations' => max((int) ($features['allocations'] ?? 1), $used + count($allocationIds)),
            ],
            'add_allocations' => array_values($allocationIds),
        ];
    }

    /**
     * @param  PelicanAllocation[]  $allocations
     */
    private function freeAt(array $allocations, string $ip, int $port): ?PelicanAllocation
    {
        foreach ($allocations as $a) {
            if (! $a->assigned && $a->port === $port && $this->ipMatches($a, $ip)) {
                return $a;
            }
        }

        return null;
    }

    /**
     * @param  PelicanAllocation[]  $allocations
     */
    private function assignedToSelf(array $allocations, string $ip, int $port, int $pelicanId): bool
    {
        foreach ($allocations as $a) {
            if ($a->port === $port && $this->ipMatches($a, $ip) && $a->serverId === $pelicanId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lowest free (P, P+offset) pair on the same IP.
     *
     * @param  PelicanAllocation[]  $allocations
     * @return array{0: PelicanAllocation, 1: PelicanAllocation}|null
     */
    private function freePair(array $allocations, string $ip, int $offset): ?array
    {
        $free = [];
        foreach ($allocations as $a) {
            if (! $a->assigned && $this->ipMatches($a, $ip)) {
                $free[$a->port] = $a;
            }
        }
        ksort($free);
        foreach ($free as $port => $a) {
            if (isset($free[$port + $offset])) {
                return [$a, $free[$port + $offset]];
            }
        }

        return null;
    }

    private function ipMatches(PelicanAllocation $a, string $ip): bool
    {
        return $a->ip === $ip || $a->ipAlias === $ip;
    }
}
