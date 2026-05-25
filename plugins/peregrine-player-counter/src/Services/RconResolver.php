<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use App\Services\Pelican\PelicanNetworkService;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;

/**
 * One-click "Resolve RCON" action: when an RCON-counted game (ARK) can't be
 * reached because its RCON port has no reachable allocation, this finds the
 * RCON port startup variable, creates a fresh Pelican allocation, points the
 * variable at the new port and restarts the server cleanly. Destructive — the
 * caller must confirm; defence-in-depth requires the server to be running.
 */
class RconResolver
{
    private const NS = PlayerCounterServiceProvider::PLUGIN_ID;

    public function __construct(
        private PelicanClientService $client,
        private PelicanNetworkService $network,
        private ServerPlayerCountService $players,
    ) {}

    /**
     * @return array{ok: bool, message?: string, error?: string, port?: int, variable?: string}
     */
    public function resolve(Server $server): array
    {
        if (! $server->identifier) {
            return ['ok' => false, 'error' => 'no_identifier'];
        }

        // Defence-in-depth: only act on a fully-running server (the UI also gates
        // on the live WS state). A restart of a stopped/installing server is wrong.
        try {
            if ($this->client->getServerResources($server->identifier)->state !== 'running') {
                return ['ok' => false, 'error' => 'not_running'];
            }
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'read_state_failed'];
        }

        try {
            $vars = $this->client->getStartupVariables($server->identifier);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'read_vars_failed'];
        }

        $rconVar = $this->findRconVariable($vars);
        if ($rconVar === null) {
            return ['ok' => false, 'error' => 'no_rcon_variable'];
        }

        // Clean, specific error when the server is already at its allocation
        // limit — rather than letting addAllocation throw a raw Pelican message.
        try {
            $used = count($this->network->listAllocations($server->identifier));
            $limit = (int) ($this->client->getRawServer($server->identifier)['feature_limits']['allocations'] ?? 0);
            if ($limit > 0 && $used >= $limit) {
                return ['ok' => false, 'error' => 'no_free_allocation'];
            }
        } catch (\Throwable) {
            // Non-fatal — let addAllocation surface the real failure below.
        }

        try {
            $allocation = $this->network->addAllocation($server->identifier);
        } catch (\Throwable) {
            // The common cause is "no free port / allocation limit reached".
            return ['ok' => false, 'error' => 'no_free_allocation'];
        }

        $attrs = $allocation['attributes'] ?? $allocation;
        $port = (int) ($attrs['port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            return ['ok' => false, 'error' => 'allocation_failed'];
        }

        try {
            $this->client->updateStartupVariable($server->identifier, $rconVar, (string) $port);
            $this->client->setPowerState($server->identifier, 'restart');
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'apply_failed'];
        }

        $this->players->invalidate($server);

        return ['ok' => true, 'message' => 'rcon_resolved', 'port' => $port, 'variable' => $rconVar];
    }

    /**
     * Find the RCON port variable: prefer the configured candidates, then any
     * variable whose name mentions both RCON and PORT, then any RCON variable.
     *
     * @param  array<int, array<string, mixed>>  $vars
     */
    private function findRconVariable(array $vars): ?string
    {
        $names = [];
        foreach ($vars as $v) {
            $key = $v['env_variable'] ?? null;
            if (is_string($key) && $key !== '') {
                $names[] = $key;
            }
        }

        foreach ((array) config(self::NS.'.rcon.port_vars', []) as $candidate) {
            if (in_array($candidate, $names, true)) {
                return $candidate;
            }
        }
        foreach ($names as $key) {
            if (stripos($key, 'rcon') !== false && stripos($key, 'port') !== false) {
                return $key;
            }
        }
        foreach ($names as $key) {
            if (stripos($key, 'rcon') !== false) {
                return $key;
            }
        }

        return null;
    }
}
