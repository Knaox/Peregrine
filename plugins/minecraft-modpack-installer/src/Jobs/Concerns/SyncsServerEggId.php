<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Jobs\Concerns;

use App\Events\Mirror\ServerMirrorChanged;
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

        if ($localEggId === null) {
            // Logging at warning level (was a silent return) so the
            // operator notices when the modpack-installer egg never made
            // it into Peregrine's local mirror — that's the failure mode
            // where the UI keeps showing the original egg throughout an
            // install while Pelican has already swapped to the installer
            // egg. EggResolver tries one auto-sync on miss; if that fell
            // through we surface it here rather than swallow.
            $logger->warning('modpack: local egg_id resolution returned null after auto-sync — UI will show stale egg until next reconcile', [
                'server_id' => $server->id,
                'pelican_egg_id' => $pelicanEggId,
            ]);

            return;
        }
        if ((int) $server->egg_id === $localEggId) {
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

    /**
     * Mirror the Pelican install/reinstall flag into Peregrine's local
     * `servers.status` so the panel UI shows the server as "provisioning"
     * (ready/active toggles to a spinner) for the duration of the modpack
     * operation. The status enum allows: provisioning, active, suspended,
     * terminated, running, stopped, offline, provisioning_failed.
     *
     * `provisioning` is the value Peregrine itself uses while a server is
     * being created — the same UI affordance applies cleanly to a modpack
     * install/uninstall.
     */
    private function setLocalServerStatus(Server $server, string $status, LoggerInterface $logger): void
    {
        if ((string) $server->status === $status) {
            return;
        }
        try {
            $server->forceFill(['status' => $status])->save();
        } catch (Throwable $e) {
            $logger->warning('modpack: local server status update failed', [
                'server_id' => $server->id,
                'target_status' => $status,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Broadcast mirror.changed so the React shell's
        // `useServerLiveUpdates` hook invalidates ['servers', id, 'server']
        // and re-runs the sidebar install gate (only Console + Home stay
        // visible while servers.status === 'provisioning'). Without this
        // the user only sees the locked sidebar after a full page reload.
        try {
            event(new ServerMirrorChanged(
                serverId: (int) $server->id,
                resource: ServerMirrorChanged::RESOURCE_SERVER,
                action: ServerMirrorChanged::ACTION_UPSERT,
                resourceId: (int) $server->id,
                accessUserIds: $server->accessUsers()->pluck('users.id')->all(),
            ));
        } catch (Throwable $e) {
            $logger->info('modpack: ServerMirrorChanged broadcast failed (non-fatal)', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
