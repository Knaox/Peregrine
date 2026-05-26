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
            return $this->planVar($this->rconPortVars(), 'rcon', $gamePort, $allocatedPorts, $startupVars);
        }

        return match ($mode) {
            'same' => ['send_port' => $gamePort, 'reachable' => true, 'kind' => 'none'],
            'offset' => $this->planOffset((int) ($portRule['value'] ?? 0), $primary, $allocatedPorts),
            'var' => $this->planVar(
                $this->queryPortVars((string) ($portRule['env'] ?? '')),
                'query',
                $gamePort,
                $allocatedPorts,
                $startupVars,
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
     * Query/RCON port is redirectable through a startup variable. The variable
     * is matched by name (configured candidates first, then any var whose name
     * mentions both the keyword — 'query'/'rcon' — and 'port'). If it's found
     * but holds no allocated port (empty default, or a local port never tied to
     * an allocation — the common Sons of the Forest QUERY_PORT case), it's NOT
     * reachable: the resolver allocates a port and repoints the variable. Only
     * when the variable already points at an allocated port is it reachable.
     *
     * @param  list<string>  $candidates  variable-name candidates
     * @param  string  $keyword  fuzzy name keyword ('query' | 'rcon')
     * @param  int  $fallbackPort  port to query before resolution (fixed/game port)
     * @param  list<int>  $allocatedPorts
     * @param  array<string, string>  $startupVars
     * @return QueryPlan
     */
    private function planVar(array $candidates, string $keyword, int $fallbackPort, array $allocatedPorts, array $startupVars): array
    {
        $name = $this->findVar($candidates, $keyword, $startupVars);

        // No redirectable variable at all: the port can't be reconfigured, so
        // it's only reachable if the fallback port happens to be allocated.
        if ($name === null) {
            return [
                'send_port' => $fallbackPort,
                'reachable' => in_array($fallbackPort, $allocatedPorts, true),
                'kind' => 'unreachable',
            ];
        }

        $value = (int) ($startupVars[$name] ?? 0);
        $valid = $value >= 1 && $value <= 65535;

        return [
            'send_port' => $valid ? $value : $fallbackPort,
            'reachable' => $valid && in_array($value, $allocatedPorts, true),
            'kind' => 'var',
            'env' => $name,
        ];
    }

    /**
     * Absolute fixed query port (Sons of the Forest 27016, Enshrouded 15637…).
     * These games expose a configurable query-port variable, so we resolve it
     * exactly like the 'var' case, querying the fixed port until then.
     *
     * @param  list<int>  $allocatedPorts
     * @param  array<string, string>  $startupVars
     * @return QueryPlan
     */
    private function planFixed(int $fixedPort, array $allocatedPorts, array $startupVars, int $gamePort): array
    {
        $fallback = ($fixedPort >= 1 && $fixedPort <= 65535) ? $fixedPort : $gamePort;

        return $this->planVar($this->queryPortVars(''), 'query', $fallback, $allocatedPorts, $startupVars);
    }

    /**
     * Find the startup variable to redirect: a configured candidate first, then
     * any variable whose name mentions both the keyword and 'port'. Returns the
     * variable NAME even when its value is empty — its mere presence means the
     * port is reconfigurable (and should be resolved).
     *
     * @param  list<string>  $candidates
     * @param  array<string, string>  $startupVars
     */
    private function findVar(array $candidates, string $keyword, array $startupVars): ?string
    {
        foreach ($candidates as $name) {
            if (array_key_exists($name, $startupVars)) {
                return $name;
            }
        }

        foreach (array_keys($startupVars) as $name) {
            if (stripos($name, $keyword) !== false && stripos($name, 'port') !== false) {
                return $name;
            }
        }

        return null;
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
