<?php

declare(strict_types=1);

namespace Plugins\PeregrinePlayerCounter\Services;

use App\Models\Server;
use App\Services\Pelican\PelicanClientService;
use App\Services\Pelican\PelicanNetworkService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Plugins\PeregrinePlayerCounter\Jobs\ResolveQueryAccessJob;
use Plugins\PeregrinePlayerCounter\PlayerCounterServiceProvider;
use Plugins\PeregrinePlayerCounter\Settings\PlayerCounterSettings;

/**
 * Resolves the live connected-player count for a server and caches it in Redis.
 * The plugin's server-home section polls this; the cache TTL (config
 * `cache_ttl`) controls freshness and shields the upstream (the Epic API for
 * EOS games) from rate-limiting.
 *
 * @phpstan-type PlayerPayload array{online: ?int, max: ?int, state: string, family: string, queryable: bool, rcon: bool, name: ?string, players: list<string>, detail: ?string, queried_at: string}
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
                        $map[$key] = (string) ($v['server_value'] ?? '');
                    }
                }
            } catch (\Throwable) {
                // Pelican unreachable — treat as no variables (best effort).
            }

            return $map;
        });
    }

    /**
     * One-time, marker-guarded dispatch of the query-port auto-resolver. The
     * marker prevents restart-spam on a genuinely-unresolvable game; once
     * resolved the query succeeds, so this branch stops firing on its own.
     *
     * @param  array{type: ?string, family: string, queryable: bool, query_port: array{mode: string}}  $target
     */
    private function autoResolve(Server $server, array $target, bool $isRcon): void
    {
        if (! (bool) config(self::NS.'.auto_resolve.enabled', true)) {
            return;
        }

        // RCON and same-port games never need a port reallocated.
        if (! $isRcon && in_array($target['query_port']['mode'] ?? 'same', ['same'], true)) {
            return;
        }

        $marker = "pc_resolve_attempted:{$server->id}";
        if (Cache::has($marker)) {
            return;
        }
        Cache::put($marker, true, 3600);

        // Drop the cached startup vars so the next query reflects the new port.
        Cache::forget("pc_vars:{$server->id}");

        ResolveQueryAccessJob::dispatch($server);
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

        // Query failed: the port may simply not be allocated yet. Kick off the
        // one-time auto-resolver (off the request path, marker-guarded) so the
        // admin/player has nothing to do. It re-checks reachability itself, so a
        // spurious trigger is a safe no-op.
        $this->autoResolve($server, $target, $isRcon);

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
