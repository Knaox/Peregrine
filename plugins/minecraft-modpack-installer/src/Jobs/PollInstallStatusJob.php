<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Plugins\MinecraftModpackInstaller\Enums\ModpackInstallationStatus;
use Plugins\MinecraftModpackInstaller\Events\InstallationCompleted;
use Plugins\MinecraftModpackInstaller\Events\InstallationFailed;
use Plugins\MinecraftModpackInstaller\Events\UninstallationCompleted;
use Plugins\MinecraftModpackInstaller\Jobs\Concerns\SyncsServerEggId;
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;
use Plugins\MinecraftModpackInstaller\Pelican\PelicanClient;
use Plugins\MinecraftModpackInstaller\Services\EggImporter;
use Plugins\MinecraftModpackInstaller\Services\JavaVersionDetectionService;
use Plugins\MinecraftModpackInstaller\Services\ModpackSettingsService;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Polls the Pelican Application API for the install completion of a modpack
 * operation. Re-dispatches itself with a delay until the server status leaves
 * `installing` or the configured timeout fires.
 */
class PollInstallStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SyncsServerEggId;

    public int $tries = 1;

    public int $timeout = 60;

    private const POLL_DELAY_SECONDS = 15;

    public function __construct(public readonly int $installationId) {}

    public function handle(
        PelicanClient $pelican,
        ModpackSettingsService $settings,
        JavaVersionDetectionService $javaDetection,
        EggImporter $eggImporter,
        LoggerInterface $logger,
    ): void {
        $installation = ModpackInstallation::with('server')->find($this->installationId);
        if ($installation === null) {
            return;
        }

        $isInstall = $installation->status === ModpackInstallationStatus::Installing;
        $isUninstall = $installation->status === ModpackInstallationStatus::Uninstalling;
        $isReinstall = $installation->status === ModpackInstallationStatus::Reinstalling;
        if (! $isInstall && ! $isUninstall && ! $isReinstall) {
            return;
        }

        $server = $installation->server;
        if ($server === null || $server->pelican_server_id === null) {
            $this->markFailed($installation, 'server_not_linked_to_pelican');

            return;
        }

        $timeoutAt = $installation->started_at?->copy()->addMinutes($settings->installTimeoutMinutes());
        if ($timeoutAt !== null && now()->greaterThan($timeoutAt)) {
            $this->markFailed($installation, 'timeout');
            $this->setLocalServerStatus($server, 'provisioning_failed', $logger);

            return;
        }

        try {
            $raw = $pelican->getServerRaw((int) $server->pelican_server_id);
            $status = (string) (($raw['attributes']['status'] ?? null) ?? '');
        } catch (Throwable $e) {
            $logger->info('modpack: poll failed (will retry)', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
            $this->reschedule();

            return;
        }

        if ($status === 'installing') {
            $this->reschedule();

            return;
        }

        if ($status === 'install_failed' || $status === 'reinstall_failed') {
            $reason = match (true) {
                $isInstall => 'install_script_failed',
                $isUninstall => 'uninstall_wipe_failed',
                $isReinstall => 'uninstall_reinstall_failed',
                default => 'unknown_phase_failed',
            };
            $this->markFailed($installation, $reason);
            $this->setLocalServerStatus($server, 'provisioning_failed', $logger);
            // Roll back to the user's original egg whenever we still own
            // the installer egg — i.e. install or wipe phase. The
            // reinstall phase is already on the user's egg, so no rollback
            // would help.
            if ($isInstall || $isUninstall) {
                $this->bestEffortRollback($installation, $pelican, $logger);
            }

            return;
        }

        if ($isInstall) {
            $this->finalizeInstall($installation, $pelican, $eggImporter, $javaDetection, $logger);
        } elseif ($isUninstall) {
            $this->beginUninstallPhase2($installation, $pelican, $eggImporter, $logger);
        } else {
            // $isReinstall — phase 2 done, server is back on the user's
            // original egg with a fresh install. Drop the row.
            $this->finalizeUninstall($installation, $logger);
        }
    }

    private function reschedule(): void
    {
        self::dispatch($this->installationId)->delay(now()->addSeconds(self::POLL_DELAY_SECONDS));
    }

    private function finalizeInstall(
        ModpackInstallation $installation,
        PelicanClient $pelican,
        EggImporter $eggImporter,
        JavaVersionDetectionService $javaDetection,
        LoggerInterface $logger,
    ): void {
        $server = $installation->server;
        $serverId = (int) $server->pelican_server_id;

        try {
            $java = $javaDetection->detect($server, 'server.jar');
        } catch (Throwable $e) {
            $logger->info('modpack: java detection threw, using fallback', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
            $java = 17;
        }

        // Scrub BB_MODPACK_* values before the egg swap. While we're still on
        // the installer egg these keys can be safely overwritten with their
        // permissive defaults (provider→modrinth, ids→'_', purge→0, op→
        // install). After the swap, even though Pelican filters env by
        // current egg when sending to Wings, the server_variables rows
        // still surface in admin UIs and panel debug tools.
        try {
            $pelican->scrubInstallerEnvironment(
                $serverId,
                $eggImporter->ensureImported(),
                'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
            );
        } catch (Throwable $e) {
            $logger->warning('modpack: BB_MODPACK_* scrub failed (continuing)', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
        }

        try {
            $pelican->updateServerStartup($serverId, [
                'egg' => $installation->pelican_egg_snapshot_id,
                'image' => $this->pickJavaImage($java),
                'startup' => $installation->pelican_startup_snapshot
                    ?? 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                'environment' => array_replace(
                    is_array($installation->pelican_environment_snapshot)
                        ? $installation->pelican_environment_snapshot
                        : [],
                    // Modpack install symlinks /mnt/server/server.jar → the
                    // real loader jar. Override SERVER_JARFILE so the
                    // original egg's startup command runs the symlink.
                    ['SERVER_JARFILE' => 'server.jar'],
                ),
                // skip_scripts MUST stay false: Pelican's
                // StartupModificationService never triggers an install on
                // PATCH (only POST /settings/reinstall does), so the flag
                // has no benefit here, and it persists on the server row —
                // every subsequent native /reinstall would then be silently
                // skipped (Pelican fires Server\Installed instantly without
                // running the egg's install script). See server 10 in the
                // logs at 18:09:34 for the exact failure mode.
                'skip_scripts' => false,
            ]);
        } catch (Throwable $e) {
            $logger->error('modpack: swap-back failed', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
            $this->markFailed($installation, 'swap_back_failed: '.$e->getMessage());
            $this->setLocalServerStatus($server, 'provisioning_failed', $logger);

            return;
        }

        // skip_scripts=true → no Server\Installed webhook fires, so we have
        // to mirror the egg swap into Peregrine's local DB ourselves. Without
        // this, servers.egg_id stays stuck on the installer egg id even
        // though Pelican is back on the user's original egg.
        $this->syncLocalEggId(
            $server,
            (int) $installation->pelican_egg_snapshot_id,
            $logger,
        );
        // Restore the server to active so the panel UI clears the spinner.
        $this->setLocalServerStatus($server, 'active', $logger);

        $installation->update([
            'status' => ModpackInstallationStatus::Completed->value,
            'java_version' => $java,
            'completed_at' => now(),
            'status_message' => null,
        ]);

        event(new InstallationCompleted($installation));
    }

    /**
     * Phase 2 trigger: the wipe is done, swap the server back onto the
     * user's original egg with skip_scripts=false so Pelican repopulates
     * /mnt/server from the egg's normal install script. We move into
     * `Reinstalling` and reschedule the poll to track the reinstall.
     */
    private function beginUninstallPhase2(
        ModpackInstallation $installation,
        PelicanClient $pelican,
        EggImporter $eggImporter,
        LoggerInterface $logger,
    ): void {
        $server = $installation->server;
        if ($server === null || $installation->pelican_egg_snapshot_id === null) {
            $this->markFailed($installation, 'phase2_missing_snapshot');

            return;
        }

        // Same scrub as install finalize: drop the BB_MODPACK_* installer
        // values before the user's egg comes back, otherwise they linger
        // in server_variables and surface in panel admin UIs.
        try {
            $pelican->scrubInstallerEnvironment(
                (int) $server->pelican_server_id,
                $eggImporter->ensureImported(),
                'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
            );
        } catch (Throwable $e) {
            $logger->warning('modpack: BB_MODPACK_* scrub failed (continuing)', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
        }

        try {
            $pelican->updateServerStartup((int) $server->pelican_server_id, [
                'egg' => $installation->pelican_egg_snapshot_id,
                'image' => $installation->pelican_image_snapshot ?? 'ghcr.io/pelican-eggs/yolks:java_17',
                'startup' => $installation->pelican_startup_snapshot
                    ?? 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                // Restore the original SERVER_JARFILE — Pelican is about to
                // run the original egg's install script on an empty
                // /mnt/server (just wiped in phase 1), so the egg expects
                // its own jar name (paper.jar / fabric-server-launch.jar
                // / etc.), not our removed symlink.
                'environment' => array_replace(
                    is_array($installation->pelican_environment_snapshot)
                        ? $installation->pelican_environment_snapshot
                        : [],
                    ['SERVER_JARFILE' => $installation->pelican_jarfile_snapshot ?? 'server.jar'],
                ),
                'skip_scripts' => false,
            ]);

            // Reinstall (skip_scripts=false) will fire the Server\Installed
            // webhook on completion, but we mirror eagerly so the panel UI
            // reflects the user's original egg right away.
            $this->syncLocalEggId(
                $server,
                (int) $installation->pelican_egg_snapshot_id,
                $logger,
            );
            // Stay in provisioning until phase 2 reinstall completes.
            $this->setLocalServerStatus($server, 'provisioning', $logger);

            $pelican->reinstallServer((int) $server->pelican_server_id);
        } catch (Throwable $e) {
            $logger->error('modpack: uninstall phase 2 dispatch failed', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
            $this->markFailed($installation, 'phase2_dispatch_failed: '.$e->getMessage());
            $this->setLocalServerStatus($server, 'provisioning_failed', $logger);

            return;
        }

        $installation->update([
            'status' => ModpackInstallationStatus::Reinstalling->value,
            'status_message' => null,
        ]);

        $this->reschedule();
    }

    private function finalizeUninstall(ModpackInstallation $installation, LoggerInterface $logger): void
    {
        $server = $installation->server;

        if ($server !== null) {
            // Phase 2 reinstall on the user's original egg fired the
            // Server\Installed webhook which already nulled `status` —
            // but make sure the panel doesn't keep showing provisioning
            // if the webhook didn't make it through.
            $this->setLocalServerStatus($server, 'active', $logger);
        }

        try {
            event(new UninstallationCompleted($server));
        } catch (Throwable $e) {
            $logger->info('modpack: uninstall event failed (non-fatal)', ['error' => $e->getMessage()]);
        }

        $installation->delete();
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
                'startup' => $installation->pelican_startup_snapshot ?? 'java -jar {{SERVER_JARFILE}}',
                'environment' => $installation->pelican_environment_snapshot
                    ?? ['SERVER_JARFILE' => 'server.jar'],
                // See finalizeInstall: skip_scripts must NOT be left true
                // or it persists on the server row and breaks every
                // future native reinstall.
                'skip_scripts' => false,
            ]);

            $this->syncLocalEggId(
                $installation->server,
                (int) $installation->pelican_egg_snapshot_id,
                $logger,
            );
        } catch (Throwable $e) {
            $logger->warning('modpack: rollback after failed install failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function pickJavaImage(int $java): string
    {
        return match ($java) {
            8 => 'ghcr.io/pelican-eggs/yolks:java_8',
            11 => 'ghcr.io/pelican-eggs/yolks:java_11',
            17 => 'ghcr.io/pelican-eggs/yolks:java_17',
            21 => 'ghcr.io/pelican-eggs/yolks:java_21',
            default => 'ghcr.io/pelican-eggs/yolks:java_17',
        };
    }
}
