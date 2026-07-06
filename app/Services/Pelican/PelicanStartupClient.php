<?php

namespace App\Services\Pelican;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

/**
 * Startup-command surface of the Pelican Application API.
 *
 * Pelican (PR #1656, v1.0.0-beta26+) replaced the egg's single `startup`
 * with a named map `startup_commands` ({"Default": "java …", "Beta": "…"}).
 * The server keeps ONE active command (`servers.startup`); players may
 * switch between the egg-defined commands — never free-typed ones (raw
 * editing was refused upstream for security, and Peregrine follows suit).
 *
 * Lives in its own file (not PelicanInfrastructureClient, already past the
 * 300-line rule).
 */
class PelicanStartupClient
{
    private const CONTEXT_CACHE_TTL_SECONDS = 60;

    /**
     * Display reads survive a Pelican outage/throttle window by falling back
     * to the last successful snapshot — without it, one failed re-read after
     * the 60s cache expired turned into a 500 and the startup card vanished
     * from the overview. 24h is plenty: the snapshot refreshes on every
     * successful read and is only ever used for DISPLAY, never for a PATCH.
     */
    private const LAST_GOOD_TTL_SECONDS = 86400;

    public function __construct(
        private readonly PelicanHttpClient $http,
        private readonly PelicanInfrastructureClient $infrastructure,
    ) {}

    /**
     * Cached view of the server's container (egg/startup/image/environment)
     * for DISPLAY paths — the overview card re-reads it on every visit, and
     * without a cache each page load costs one Pelican Application call.
     * NEVER used to build a PATCH (a stale environment would overwrite
     * variables edited in between): writes read fresh, then invalidate this.
     *
     * @return array{egg: int, image: string, startup: string, environment: array<string, string>, egg_docker_images: array<string, string>}
     *
     * @throws RequestException
     */
    public function getServerStartupContext(int $pelicanServerId): array
    {
        return Cache::remember(
            "peregrine:server-startup-context:{$pelicanServerId}",
            now()->addSeconds(self::CONTEXT_CACHE_TTL_SECONDS),
            fn (): array => $this->resolveContextWithFallback($pelicanServerId),
        );
    }

    /**
     * Fresh read, falling back to the last good snapshot when Pelican is
     * unreachable or throttling. Only a cold failure (no snapshot yet)
     * still bubbles up.
     *
     * @return array{egg: int, image: string, startup: string, environment: array<string, string>, egg_docker_images: array<string, string>}
     *
     * @throws RequestException
     */
    private function resolveContextWithFallback(int $pelicanServerId): array
    {
        $lastGoodKey = "peregrine:server-startup-context-last-good:{$pelicanServerId}";

        try {
            $fresh = $this->infrastructure->getServerContainer($pelicanServerId);
        } catch (\Throwable $e) {
            $stale = Cache::get($lastGoodKey);
            if (is_array($stale)) {
                return $stale;
            }

            throw $e;
        }

        Cache::put($lastGoodKey, $fresh, now()->addSeconds(self::LAST_GOOD_TTL_SECONDS));

        return $fresh;
    }

    public function forgetServerStartupContext(int $pelicanServerId): void
    {
        Cache::forget("peregrine:server-startup-context:{$pelicanServerId}");
    }

    /**
     * Named startup commands defined on an egg, in the egg's declared order.
     * Falls back to the legacy single `startup` field for pre-beta26 Pelican
     * installs. Cached 5 min per egg (same policy as getEggDockerImages).
     *
     * @return array<string, string> name → command
     *
     * @throws RequestException
     */
    public function getEggStartupOptions(int $pelicanEggId): array
    {
        if ($pelicanEggId <= 0) {
            return [];
        }

        return Cache::remember(
            "peregrine:egg-startup-commands:{$pelicanEggId}",
            now()->addMinutes(5),
            fn (): array => $this->resolveEggOptionsWithFallback($pelicanEggId),
        );
    }

    /**
     * Fresh read of the egg command map, falling back to the last non-empty
     * snapshot when Pelican is unreachable or throttling (same policy as the
     * server context — display must survive a transient outage).
     *
     * @return array<string, string> name → command
     *
     * @throws RequestException
     */
    private function resolveEggOptionsWithFallback(int $pelicanEggId): array
    {
        $lastGoodKey = "peregrine:egg-startup-commands-last-good:{$pelicanEggId}";

        try {
            $response = $this->http->request()
                ->get("/api/application/eggs/{$pelicanEggId}")
                ->throw();
        } catch (\Throwable $e) {
            $stale = Cache::get($lastGoodKey);
            if (is_array($stale)) {
                return $stale;
            }

            throw $e;
        }

        $commands = $response->json('attributes.startup_commands');
        $options = is_array($commands) ? $this->normaliseCommands($commands) : [];

        if ($options === []) {
            // Pre-beta26 Pelican: single startup string.
            $legacy = $response->json('attributes.startup');
            $options = is_string($legacy) && trim($legacy) !== '' ? ['Default' => $legacy] : [];
        }

        if ($options !== []) {
            Cache::put($lastGoodKey, $options, now()->addSeconds(self::LAST_GOOD_TTL_SECONDS));
        }

        return $options;
    }

    /**
     * Point the server at another startup command. Same PATCH strategy as
     * the Docker-image quick-fix: resend egg/environment/image verbatim,
     * override only `startup`, skip install scripts. Wings reads the new
     * command on the next server start — nothing is restarted here.
     *
     * @param  array{egg: int, image: string, environment: array<string, string>}|null  $container
     *                                                                                              Reuse a container payload the caller already fetched (saves one API call).
     *
     * @throws RequestException
     */
    public function updateServerStartupCommand(int $pelicanServerId, string $newStartup, ?array $container = null): void
    {
        $newStartup = trim($newStartup);
        if ($newStartup === '') {
            throw new \InvalidArgumentException('Refusing to apply an empty startup command.');
        }

        $container ??= $this->infrastructure->getServerContainer($pelicanServerId);
        if (($container['egg'] ?? 0) <= 0) {
            throw new \RuntimeException('Could not read the current egg id from Pelican.');
        }

        $payload = [
            'egg' => $container['egg'],
            'startup' => $newStartup,
            'environment' => $container['environment'] ?? [],
            'skip_scripts' => true,
        ];
        // Only resend the image when Pelican surfaced one — an empty string
        // would overwrite the server's image instead of preserving it.
        if (($container['image'] ?? '') !== '') {
            $payload['image'] = $container['image'];
        }

        $this->http->request()
            ->patch("/api/application/servers/{$pelicanServerId}/startup", $payload)
            ->throw();

        // The display cache now holds the pre-switch command — drop it so the
        // SPA's immediate refetch reads the fresh state.
        $this->forgetServerStartupContext($pelicanServerId);
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return array<string, string>
     */
    private function normaliseCommands(array $raw): array
    {
        $out = [];
        foreach ($raw as $name => $command) {
            if (is_string($name) && trim($name) !== '' && is_string($command) && trim($command) !== '') {
                $out[trim($name)] = $command;
            }
        }

        return $out;
    }
}
