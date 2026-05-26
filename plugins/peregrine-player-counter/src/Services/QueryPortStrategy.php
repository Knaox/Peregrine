<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;

/**
 * Pure, side-effect-free planner. Given a resolved query target, the server's
 * primary allocation, its full allocation set and its startup variables, it
 * works out:
 *   - which port the sidecar must actually query (`send_port`);
 *   - whether that port is already reachable (a published Pelican allocation);
 *   - and, if not, HOW the auto-resolver should make it reachable.
 *
 * The sidecar runs alongside Peregrine (not on the Wings node), so it reaches
 * game servers over the public IP — only ALLOCATED ports are published by
 * Docker and therefore queryable. That single fact drives the whole plan.
 *
 * Resolution kinds (`kind`):
 *   - 'none'      : nothing to do — the query port is the (already reachable)
 *                   primary, or it maps to an existing allocation.
 *   - 'var'       : the query port is redirectable via a startup variable
 *                   (ARK RCON_PORT, Sons of the Forest QueryPort). Allocate a
 *                   port, point the variable at it, restart. Low risk — never
 *                   touches the game port. Mirrors the ARK flow exactly.
 *   - 'adjacent'  : the query port is hard-wired to game_port + offset with no
 *                   variable (Valheim, 7DtD, The Forest). Needs an adjacent
 *                   allocation pair with the game port on the lower one.
 *   - 'unreachable': absolute fixed port, no variable to redirect and not
 *                   allocated — best-effort only (query will likely fail).
 *
 * @phpstan-import-type QueryTarget from EggGameTypeResolver
 * @phpstan-import-type PortStrategy from EggGameTypeResolver
 *
 * @phpstan-type QueryPlan array{send_port: int, reachable: bool, kind: string, env?: string, offset?: int}
 */
class QueryPortStrategy
{
    private const NS = PlayerCounterServiceProvider::PLUGIN_ID;

    /**
     * @param  PortStrategy  $portRule  the target's `query_port` block
     * @param  array{ip: string, port: int}  $primary
     * @param  list<int>  $allocatedPorts  every published allocation port
     * @param  array<string, string>  $startupVars  env_variable => server_value
     * @return QueryPlan
     */
    public function plan(array $portRule, bool $isRcon, array $primary, array $allocatedPorts, array $startupVars): array
    {
        $mode = (string) ($portRule['mode'] ?? 'same');
        $gamePort = $primary['port'];

        // RCON-counted games read their port from a startup variable regardless
        // of the catalogue's wire strategy — treat them as the 'var' case.
        if ($isRcon) {
            return $this->planVar($this->rconPortVars(), $allocatedPorts, $startupVars, $gamePort);
        }

        return match ($mode) {
            'same' => ['send_port' => $gamePort, 'reachable' => true, 'kind' => 'none'],
            'offset' => $this->planOffset((int) ($portRule['value'] ?? 0), $primary, $allocatedPorts),
            'var' => $this->planVar(
                $this->queryPortVars((string) ($portRule['env'] ?? '')),
                $allocatedPorts,
                $startupVars,
                $gamePort,
            ),
            'fixed' => $this->planFixed((int) ($portRule['value'] ?? 0), $allocatedPorts, $startupVars, $gamePort),
            default => ['send_port' => $gamePort, 'reachable' => true, 'kind' => 'none'],
        };
    }

    /**
     * Cheap hot-path companion to plan(): the port to hand the sidecar, without
     * the reachability check (which needs the throttled allocation list). For
     * 'same'/'offset' this is just the game port — no startup-var read needed.
     *
     * @param  PortStrategy  $portRule
     * @param  array<string, string>  $startupVars  may be empty for same/offset
     */
    public function sendPort(array $portRule, bool $isRcon, int $gamePort, array $startupVars): int
    {
        $plan = $this->plan($portRule, $isRcon, ['ip' => '', 'port' => $gamePort], [], $startupVars);

        return $plan['send_port'];
    }

    /**
     * Whether resolving the send port requires reading the (throttled) startup
     * variables — true only for RCON or variable/fixed-port games.
     *
     * @param  PortStrategy  $portRule
     */
    public function needsStartupVars(array $portRule, bool $isRcon): bool
    {
        return $isRcon || in_array((string) ($portRule['mode'] ?? 'same'), ['var', 'fixed'], true);
    }

    /**
     * Query port = game port + offset, hard-wired (Valheim +1). Reachable only
     * if that exact adjacent port is already allocated.
     *
     * @param  array{ip: string, port: int}  $primary
     * @param  list<int>  $allocatedPorts
     * @return QueryPlan
     */
    private function planOffset(int $offset, array $primary, array $allocatedPorts): array
    {
        $queryPort = $primary['port'] + $offset;

        return [
            'send_port' => $primary['port'], // GameDig adds the offset itself.
            'reachable' => in_array($queryPort, $allocatedPorts, true),
            'kind' => 'adjacent',
            'offset' => $offset,
        ];
    }

    /**
     * Query port is redirectable through a startup variable. Reachable if the
     * variable already points at an allocated port; otherwise the resolver must
     * allocate one and repoint the variable.
     *
     * @param  list<string>  $candidates  variable-name candidates
     * @param  list<int>  $allocatedPorts
     * @param  array<string, string>  $startupVars
     * @return QueryPlan
     */
    private function planVar(array $candidates, array $allocatedPorts, array $startupVars, int $gamePort): array
    {
        foreach ($candidates as $name) {
            $value = isset($startupVars[$name]) ? (int) $startupVars[$name] : 0;
            if ($value >= 1 && $value <= 65535) {
                return [
                    'send_port' => $value,
                    'reachable' => in_array($value, $allocatedPorts, true),
                    'kind' => 'var',
                    'env' => $name,
                ];
            }
        }

        // No usable variable found — fall back to the game port (best effort).
        return ['send_port' => $gamePort, 'reachable' => true, 'kind' => 'none'];
    }

    /**
     * Absolute fixed query port (Sons of the Forest 27016). If a query-port
     * variable exists we treat it as the 'var' case (allocate + redirect);
     * otherwise it's only reachable if that exact port happens to be allocated.
     *
     * @param  list<int>  $allocatedPorts
     * @param  array<string, string>  $startupVars
     * @return QueryPlan
     */
    private function planFixed(int $fixedPort, array $allocatedPorts, array $startupVars, int $gamePort): array
    {
        $vars = $this->queryPortVars('');
        foreach ($vars as $name) {
            if (isset($startupVars[$name])) {
                return $this->planVar([$name], $allocatedPorts, $startupVars, $gamePort);
            }
        }

        if ($fixedPort >= 1 && $fixedPort <= 65535) {
            return [
                'send_port' => $fixedPort,
                'reachable' => in_array($fixedPort, $allocatedPorts, true),
                'kind' => 'unreachable',
            ];
        }

        return ['send_port' => $gamePort, 'reachable' => true, 'kind' => 'none'];
    }

    /**
     * @return list<string>
     */
    private function rconPortVars(): array
    {
        return array_values(array_map('strval', (array) config(self::NS.'.rcon.port_vars', [])));
    }

    /**
     * Query-port variable candidates, with an explicit env name (from the rule)
     * taking precedence over the configured defaults.
     *
     * @return list<string>
     */
    private function queryPortVars(string $explicit): array
    {
        $defaults = array_map('strval', (array) config(self::NS.'.auto_resolve.query_port_vars', []));
        $candidates = $explicit !== '' ? array_merge([$explicit], $defaults) : $defaults;

        return array_values(array_unique($candidates));
    }
}
