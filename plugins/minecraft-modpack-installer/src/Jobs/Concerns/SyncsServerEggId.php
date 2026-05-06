<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Jobs\Concerns;

use App\Models\Server;
use App\Services\Sync\EggResolver;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Mirrors a Pelican egg id swap into Peregrine's local `servers.egg_id` so the
 * panel UI / sidebar / Filament admin reflect the correct egg right after a
 * `PelicanClient::updateServerStartup()` call.
 *
 * Pelican only emits the `Server\Installed` webhook (the normal sync vector,
 * see `App\Jobs\Bridge\SyncServerFromPelicanWebhookJob`) when a real reinstall
 * fires. Our swap-back path uses `skip_scripts: true`, so no webhook arrives
 * and Peregrine would otherwise stay desynced. This helper closes that gap.
 *
 * Re-uses `App\Services\Sync\EggResolver` so a missing local egg row triggers
 * the same one-shot egg sync as the regular webhook path.
 */
trait SyncsServerEggId
{
    private function syncLocalEggId(Server $server, ?int $pelicanEggId, LoggerInterface $logger): void
    {
        if ($pelicanEggId === null || $pelicanEggId <= 0) {
            return;
        }

        try {
            $localEggId = EggResolver::resolveLocalEggId(
                ['egg_id' => $pelicanEggId],
                (int) ($server->pelican_server_id ?? 0),
            );
        } catch (Throwable $e) {
            $logger->warning('modpack: local egg_id resolution failed', [
                'server_id' => $server->id,
                'pelican_egg_id' => $pelicanEggId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($localEggId === null || (int) $server->egg_id === $localEggId) {
            return;
        }

        try {
            $server->forceFill(['egg_id' => $localEggId])->save();
        } catch (Throwable $e) {
            $logger->warning('modpack: local egg_id update failed', [
                'server_id' => $server->id,
                'target_egg_id' => $localEggId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
