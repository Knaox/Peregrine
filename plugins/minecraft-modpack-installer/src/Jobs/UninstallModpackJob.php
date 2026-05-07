<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Plugins\MinecraftModpackInstaller\Enums\ModpackInstallationStatus;
use Plugins\MinecraftModpackInstaller\Events\InstallationFailed;
use Plugins\MinecraftModpackInstaller\Jobs\Concerns\SyncsServerEggId;
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;
use Plugins\MinecraftModpackInstaller\Pelican\PelicanClient;
use Plugins\MinecraftModpackInstaller\Services\EggImporter;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Phase 1 of the two-phase uninstall flow: swap the server onto the modpack
 * installer egg in `BB_MODPACK_OPERATION=uninstall` mode and trigger a
 * reinstall. The installer script wipes /mnt/server clean and exits 0.
 *
 * Phase 2 (swap back to the user's original egg with skip_scripts=false so
 * Pelican repopulates the directory from the egg's normal install script)
 * is handled by `PollInstallStatusJob` when it detects the wipe finished.
 */
class UninstallModpackJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SyncsServerEggId;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $installationId) {}

    public function uniqueId(): string
    {
        return "modpack-uninstall:{$this->installationId}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(
        PelicanClient $pelican,
        EggImporter $eggImporter,
        LoggerInterface $logger,
    ): void {
        $installation = ModpackInstallation::with('server')->find($this->installationId);
        if ($installation === null) {
            return;
        }
        if ($installation->status !== ModpackInstallationStatus::Uninstalling) {
            return;
        }

        $server = $installation->server;
        if ($server === null || $server->pelican_server_id === null) {
            $this->markFailed($installation, 'server_not_linked_to_pelican');

            return;
        }

        try {
            $installerEggId = $eggImporter->ensureImported();

            // Wipe-only environment: provider/id/version are required by the
            // bash script's set -u guards but unused — set to safe stubs.
            $installerEnvironment = [
                'BB_MODPACK_OPERATION' => 'uninstall',
                'BB_MODPACK_PROVIDER' => $installation->provider->value,
                'BB_MODPACK_ID' => (string) $installation->modpack_id,
                'BB_MODPACK_VERSION_ID' => (string) $installation->version_id,
                'BB_MODPACK_GAME_VERSION' => '',
                'BB_MODPACK_PURGE' => '1',
                'BB_MODPACK_CURSEFORGE_KEY' => '',
                'SERVER_JARFILE' => 'server.jar',
            ];

            $pelican->updateServerStartup((int) $server->pelican_server_id, [
                'egg' => $installerEggId,
                'image' => 'ghcr.io/pelican-eggs/yolks:java_21',
                'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                'environment' => $installerEnvironment,
                'skip_scripts' => false,
            ]);

            // Mirror the temporary installer egg id into the local DB so
            // the panel UI doesn't keep showing the old (real) egg during
            // the wipe phase. Flip status to provisioning so the panel
            // shows a spinner — we'll restore to active in finalizeUninstall
            // (which is in PollInstallStatusJob, the second phase). The
            // defensive Pelican sync that exists in PollInstallStatusJob's
            // post-install branch is intentionally NOT duplicated here:
            // we're swapping TO the installer egg, not back to the user's
            // original egg, so a webhook-driven sync would just rewrite
            // the egg_id we just wrote.
            $this->syncLocalEggId($server, $installerEggId, $logger);
            $this->setLocalServerStatus($server, 'provisioning', $logger);

            $pelican->reinstallServer((int) $server->pelican_server_id);

            // Hand off to the poll job which will detect wipe completion
            // and kick off phase 2 (swap-back + reinstall on original egg).
            PollInstallStatusJob::dispatch($installation->id)->delay(now()->addSeconds(15));
        } catch (Throwable $e) {
            $logger->error('modpack: uninstall (phase 1) failed', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
            $this->markFailed(
                $installation,
                'uninstall_dispatch_failed: '.substr($e->getMessage(), 0, 800),
            );
            if ($server !== null) {
                $this->setLocalServerStatus($server, 'provisioning_failed', $logger);
            }

            // Best-effort: try to restore the user's original egg so the
            // server isn't left stranded on the installer egg.
            $this->bestEffortRollback($installation, $pelican, $logger);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $installation = ModpackInstallation::find($this->installationId);
        if ($installation === null) {
            return;
        }
        if ($installation->status === ModpackInstallationStatus::Uninstalling) {
            $this->markFailed(
                $installation,
                'job_failed: '.substr($e->getMessage(), 0, 800),
            );
        }
    }

    private function markFailed(ModpackInstallation $installation, string $reason): void
    {
        $installation->update([
            'status' => ModpackInstallationStatus::Failed->value,
            'status_message' => $reason,
            'failed_at' => now(),
        ]);
        event(new InstallationFailed($installation, $reason));
    }

    private function bestEffortRollback(
        ModpackInstallation $installation,
        PelicanClient $pelican,
        LoggerInterface $logger,
    ): void {
        if ($installation->pelican_egg_snapshot_id === null || $installation->server === null) {
            return;
        }
        try {
            $pelican->updateServerStartup((int) $installation->server->pelican_server_id, [
                'egg' => $installation->pelican_egg_snapshot_id,
                'image' => $installation->pelican_image_snapshot ?? 'ghcr.io/pelican-eggs/yolks:java_17',
                'startup' => $installation->pelican_startup_snapshot
                    ?? 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                'environment' => $installation->pelican_environment_snapshot
                    ?? ['SERVER_JARFILE' => $installation->pelican_jarfile_snapshot ?? 'server.jar'],
                // See finalizeInstall: skip_scripts must NOT be true here or
                // it persists on the server row and breaks future reinstalls.
                'skip_scripts' => false,
            ]);

            $this->syncLocalEggId(
                $installation->server,
                (int) $installation->pelican_egg_snapshot_id,
                $logger,
            );
        } catch (Throwable $e) {
            $logger->warning('modpack: uninstall rollback failed', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}
