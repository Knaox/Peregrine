<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Pelican;

use App\Models\Egg;
use App\Models\Server;
use App\Services\Pelican\PelicanHttpClient;
use App\Services\Pelican\PelicanInfrastructureClient;
use Throwable;

/**
 * Pelican freezes a server's startup command at creation time: updating an
 * egg does NOT touch its existing servers, so an egg re-import alone leaves
 * every live server booting with the OLD command (the reason a freshly wired
 * `-SandboxCode`/config-upsert never ran on pre-existing servers). After an
 * egg import, this pushes the egg's current startup to every local server of
 * that egg — preserving each server's image and environment, and seeding
 * missing egg variables (e.g. a brand-new SANDBOX_CODE) with their defaults.
 */
final class EggStartupPropagator
{
    public function __construct(
        private readonly PelicanHttpClient $http,
        private readonly PelicanInfrastructureClient $infra,
    ) {}

    /**
     * @param  array<string, string>  $variableDefaults  env_variable => default seeded when absent
     * @return array{synced: int, skipped: int, failed: int}
     */
    public function propagate(int $pelicanEggId, string $startup, array $variableDefaults = []): array
    {
        $startup = trim($startup);
        $localEgg = $startup === '' ? null : Egg::query()->where('pelican_egg_id', $pelicanEggId)->first();
        if ($localEgg === null) {
            return ['synced' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $servers = Server::query()
            ->where('egg_id', $localEgg->id)
            ->whereNotNull('pelican_server_id')
            ->get();

        $synced = 0;
        $skipped = 0;
        $failed = 0;
        foreach ($servers as $server) {
            try {
                $container = $this->infra->getServerContainer((int) $server->pelican_server_id);
                if ($container['startup'] === $startup) {
                    $skipped++;

                    continue;
                }

                $environment = $container['environment'];
                foreach ($variableDefaults as $key => $default) {
                    if (! array_key_exists($key, $environment) || $environment[$key] === '') {
                        $environment[$key] = $default;
                    }
                }

                $this->http->request()
                    ->patch('/api/application/servers/'.(int) $server->pelican_server_id.'/startup', [
                        'egg' => $container['egg'] > 0 ? $container['egg'] : $pelicanEggId,
                        'startup' => $startup,
                        'environment' => $environment,
                        'image' => $container['image'],
                        'skip_scripts' => true,
                    ])
                    ->throw();
                $synced++;
            } catch (Throwable $e) {
                report($e);
                $failed++;
            }
        }

        return ['synced' => $synced, 'skipped' => $skipped, 'failed' => $failed];
    }
}
