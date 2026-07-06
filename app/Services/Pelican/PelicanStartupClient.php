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
    public function __construct(
        private readonly PelicanHttpClient $http,
        private readonly PelicanInfrastructureClient $infrastructure,
    ) {}

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
            function () use ($pelicanEggId): array {
                $response = $this->http->request()
                    ->get("/api/application/eggs/{$pelicanEggId}")
                    ->throw();

                $commands = $response->json('attributes.startup_commands');
                if (is_array($commands)) {
                    return $this->normaliseCommands($commands);
                }

                // Pre-beta26 Pelican: single startup string.
                $legacy = $response->json('attributes.startup');

                return is_string($legacy) && trim($legacy) !== '' ? ['Default' => $legacy] : [];
            },
        );
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
