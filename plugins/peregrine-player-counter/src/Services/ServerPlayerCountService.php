<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use App\Services\Pelican\PelicanNetworkService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;
use Plugins\PeregrinePlayerCounter\Settings\PlayerCounterSettings;

/**
 * Resolves the live connected-player count for a server and caches it in Redis.
 * The plugin's server-home section polls this; the cache TTL (config
 * `cache_ttl`) controls freshness and shields the upstream (the Epic API for
 * EOS games) from rate-limiting.
 *
 * @phpstan-type PlayerPayload array{online: ?int, max: ?int, state: string, family: string, queryable: bool, rcon: bool, resolvable: bool, name: ?string, players: list<string>, detail: ?string, queried_at: string}
 */
class ServerPlayerCountService
{
    private const NS = PlayerCounterServiceProvider::PLUGIN_ID;

    public function __construct(
        private EggGameTypeResolver $resolver,
        private GameQueryClient $client,
        private RconPlayerQuery $rcon,
        private PelicanNetworkService $networkService,
        private PelicanClientService $pelican,
        private QueryPortStrategy $strategy,
    ) {}

    /**
     * @return PlayerPayload
     */
    public function get(Server $server, bool $forceRefresh = false, bool $running = true): array
    {
        $settings = PlayerCounterSettings::make();

        // Disabled, or this server's egg isn't on the whitelist → hide the card.
        if (! $settings->enabled || ! $settings->allowsEgg($server->egg_id)) {
            return $this->payload(null, null, 'unavailable');
        }

        $target = $this->resolver->resolve($server->egg);

        // No queryable type at all (e.g. a server without an egg yet) → just
        // show offline. With `fallback_type` set, every real egg is queryable,
        // so the card always appears for a whitelisted server — counting it is
        // the admin's responsibility (they chose to whitelist that egg).
        if (! $target['queryable'] || ! is_string($target['type'])) {
            return $this->payload(null, null, 'offline', $target);
        }

        // Whitelisted but the server isn't running → report offline without
        // firing the (slow) network/RCON query.
        if (! $running) {
            return $this->payload(null, null, 'offline', $target);
        }

        $cached = Cache::get($this->cacheKey($server));

        if (is_array($cached) && ! $this->shouldRequery($cached, $forceRefresh)) {
            return $cached;
        }

        return $this->refresh($server, $target);
    }

    public function invalidate(Server $server): void
    {
        Cache::forget($this->cacheKey($server));
    }

    /**
     * Startup variables for the send-port lookup, cached to respect Pelican's
     * per-server throttle. Returns [] without any Pelican call for games whose
     * query port is the game port (same/offset), which need no variable.
     *
     * @param  array{type: ?string, family: string, queryable: bool, query_port: array{mode: string}}  $target
     * @return array<string, string>
     */
    private function startupVarsFor(Server $server, array $target, bool $isRcon): array
    {
        if (! $this->strategy->needsStartupVars($target['query_port'], $isRcon)) {
            return [];
        }

        return Cache::remember("pc_vars:{$server->id}", 600, function () use ($server): array {
            $map = [];
            try {
                foreach ($this->pelican->getStartupVariables((string) $server->identifier) as $v) {
                    $key = $v['env_variable'] ?? null;
                    if (is_string($key)) {
                        $value = $v['server_value'] ?? '';
                        $map[$key] = (string) (($value === '' || $value === null) ? ($v['default_value'] ?? '') : $value);
                    }
                }
            } catch (\Throwable) {
                // Pelican unreachable — treat as no variables (best effort).
            }

            return $map;
        });
    }

    /**
     * Whether Peregrine can fix this game's query/RCON port by reconfiguring the
     * server (allocate a port + repoint a startup variable, or move the game port
     * for adjacent-port games). Drives the card's manual "Resolve" button — we
     * never reconfigure automatically. 'same'-port games (incl. the generic A2S
     * fallback) have nothing to reconfigure: offline means genuinely empty/down.
     *
     * @param  array{type: ?string, family: string, queryable: bool, query_port?: array{mode?: string}}  $target
     */
    private function isResolvable(array $target): bool
    {
        $isRcon = is_string($target['type'] ?? null)
            && in_array($target['type'], (array) config(self::NS.'.rcon.types', []), true);

        return $isRcon || in_array($target['query_port']['mode'] ?? 'same', ['var', 'fixed', 'offset'], true);
    }

    /**
     * Read the player count from the server console (websocket via the sidecar).
     * Needs fresh websocket credentials; the short-lived connection means no
     * token refresh. Returns the sidecar's structured result.
     *
     * @param  array{count: string, name: ?string, flags: string}  $patterns
     * @return array{ok: bool, online?: int, max?: ?int, name?: ?string, players?: list<string>, error?: string}
     */
    private function consoleCount(Server $server, array $patterns): array
    {
        if (! $server->identifier) {
            return ['ok' => false, 'error' => 'no identifier'];
        }

        try {
            $ws = $this->pelican->getWebsocket($server->identifier);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'websocket credentials unavailable'];
        }

        return $this->client->console($ws->socket, $ws->token, $patterns, (string) config('app.url', ''));
    }

    private function cacheKey(Server $server): string
    {
        return 'pc_players:'.$server->id;
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function shouldRequery(array $cached, bool $forceRefresh): bool
    {
        if (($cached['queryable'] ?? false) !== true) {
            return false; // EOS-unknown / unsupported: nothing to re-query.
        }

        if ($forceRefresh) {
            return true;
        }

        $iso = is_string($cached['queried_at'] ?? null) ? $cached['queried_at'] : null;

        return $this->ageInSeconds($iso) >= (int) config(self::NS.'.cache_ttl', 60);
    }

    /**
     * @param  array{type: ?string, family: string, queryable: bool, query_port: array{mode: string}}  $target
     * @return PlayerPayload
     */
    private function refresh(Server $server, array $target): array
    {
        $allocation = $this->primaryAllocation($server);
        if (! $allocation) {
            // Transient (Pelican unreachable): don't poison the cache.
            return $this->payload(null, null, 'unknown', $target);
        }

        $isRcon = in_array($target['type'], (array) config(self::NS.'.rcon.types', []), true);

        if ($isRcon) {
            $result = $this->rcon->query($server, $allocation, $target['type']);
        } else {
            // The sidecar reaches servers over the public IP, so it must hit an
            // ALLOCATED port. QueryPortStrategy maps the catalogue's port rule
            // (same/offset/var/fixed) to the actual port to query; only var/fixed
            // games need the (throttled) startup-var read, which we cache.
            $queryPort = $this->strategy->sendPort(
                $target['query_port'],
                false,
                (int) $allocation['port'],
                $this->startupVarsFor($server, $target, false),
            );
            $result = $this->client->query($target['type'], $allocation['ip'], $queryPort, $target['family']);
        }

        if (($result['ok'] ?? false) === true) {
            return $this->store($server, $this->payload($result['online'] ?? 0, $result['max'] ?? null, 'online', $target, $result['name'] ?? null, $result['players'] ?? []));
        }

        // Console-count fallback: games with no usable wire query (e.g. crossplay
        // Valheim — PlayFab relay, no A2S listener) print an absolute count in
        // their console. Read it over the Wings websocket via the sidecar.
        if (is_array($target['console'] ?? null)) {
            $console = $this->consoleCount($server, $target['console']);
            if (($console['ok'] ?? false) === true) {
                return $this->store($server, $this->payload($console['online'] ?? 0, $console['max'] ?? null, 'online', $target, $console['name'] ?? null, $console['players'] ?? []));
            }
        }

        // Query failed. We NEVER reconfigure the server automatically: when the
        // game needs a query/RCON port that Peregrine can fix (allocate + repoint
        // a startup variable, or move the game port for adjacent-port games), the
        // payload's `resolvable` flag tells the card to show a warning + a manual
        // "Resolve" button (it restarts the server, so it's the player's choice).
        return $this->store($server, $this->payload(null, null, 'offline', $target, null, [], is_string($result['error'] ?? null) ? $result['error'] : null));
    }

    /**
     * @param  array{type: ?string, family: string, queryable: bool}|null  $target
     * @param  list<string>  $players
     * @return PlayerPayload
     */
    private function payload(?int $online, ?int $max, string $state, ?array $target = null, ?string $name = null, array $players = [], ?string $detail = null): array
    {
        $target ??= ['type' => null, 'family' => 'unknown', 'queryable' => false];

        return [
            'online' => $online,
            'max' => $max,
            'state' => $state,
            'family' => $target['family'],
            'queryable' => (bool) $target['queryable'],
            'rcon' => is_string($target['type'] ?? null)
                && in_array($target['type'], (array) config(self::NS.'.rcon.types', []), true),
            'resolvable' => $this->isResolvable($target),
            'name' => $name,
            'players' => $players,
            'detail' => $detail,
            'queried_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * @param  PlayerPayload  $payload
     * @return PlayerPayload
     */
    private function store(Server $server, array $payload): array
    {
        Cache::put($this->cacheKey($server), $payload, (int) config(self::NS.'.cache_ttl', 60));

        return $payload;
    }

    private function ageInSeconds(?string $iso): int
    {
        if (! is_string($iso)) {
            return PHP_INT_MAX;
        }

        try {
            return max(0, Carbon::now()->getTimestamp() - Carbon::parse($iso)->getTimestamp());
        } catch (\Throwable) {
            return PHP_INT_MAX;
        }
    }

    /**
     * Primary (default) allocation as the public ip:port. Reuses the cache key
     * ServerController warms, falling back to a live Pelican lookup.
     *
     * @return array{ip: string, port: int}|null
     */
    private function primaryAllocation(Server $server): ?array
    {
        if (! $server->identifier) {
            return null;
        }

        $cached = Cache::get("server_allocation:{$server->identifier}");
        if (is_array($cached) && isset($cached['ip'], $cached['port'])) {
            return ['ip' => (string) $cached['ip'], 'port' => (int) $cached['port']];
        }

        try {
            $allocations = $this->networkService->listAllocations($server->identifier);
            $primary = null;
            foreach ($allocations as $alloc) {
                $attrs = $alloc['attributes'] ?? $alloc;
                $primary ??= $attrs;
                if ($attrs['is_default'] ?? false) {
                    $primary = $attrs;
                    break;
                }
            }

            if (is_array($primary) && isset($primary['port'])) {
                return ['ip' => (string) ($primary['ip_alias'] ?? $primary['ip']), 'port' => (int) $primary['port']];
            }
        } catch (\Throwable) {
            // Pelican unreachable — caller treats this as transient 'unknown'.
        }

        return null;
    }
}
