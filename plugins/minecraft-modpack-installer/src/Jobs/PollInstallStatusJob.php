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
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;
use Plugins\MinecraftModpackInstaller\Pelican\PelicanClient;
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

    public int $tries = 1;

    public int $timeout = 60;

    private const POLL_DELAY_SECONDS = 15;

    public function __construct(public readonly int $installationId) {}

    public function handle(
        PelicanClient $pelican,
        ModpackSettingsService $settings,
        JavaVersionDetectionService $javaDetection,
        LoggerInterface $logger,
    ): void {
        $installation = ModpackInstallation::with('server')->find($this->installationId);
        if ($installation === null) {
            return;
        }

        $isInstall = $installation->status === ModpackInstallationStatus::Installing;
        $isUninstall = $installation->status === ModpackInstallationStatus::Uninstalling;
        if (! $isInstall && ! $isUninstall) {
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
            $reason = $isInstall ? 'install_script_failed' : 'uninstall_script_failed';
            $this->markFailed($installation, $reason);
            if ($isInstall) {
                $this->bestEffortRollback($installation, $pelican, $logger);
            }

            return;
        }

        if ($isInstall) {
            $this->finalizeInstall($installation, $pelican, $javaDetection, $logger);
        } else {
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
                    ['SERVER_JARFILE' => 'server.jar'],
                ),
                'skip_scripts' => true,
            ]);
        } catch (Throwable $e) {
            $logger->error('modpack: swap-back failed', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
            $this->markFailed($installation, 'swap_back_failed: '.$e->getMessage());

            return;
        }

        // Defensive belt-and-suspenders : Pelican fires `updated:Server`
        // automatically and Peregrine's webhook listener will sync the local
        // mirror on its own — but if the webhook is disabled (or Reverb is
        // down on a particular host), the local egg_id stays stale and the UI
        // shows the modpack-installer egg until next reconciler tick. Dispatch
        // the sync job manually so we own the freshness regardless.
        try {
            $apiSnapshot = $pelican->getServerRaw($serverId);
            \App\Jobs\Bridge\SyncServerFromPelicanWebhookJob::dispatch(
                'modpack: post-install sync',
                $serverId,
                $apiSnapshot['attributes'] ?? [],
            );
        } catch (Throwable $e) {
            $logger->info('modpack: defensive sync dispatch failed (non-fatal)', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
        }

        $installation->update([
            'status' => ModpackInstallationStatus::Completed->value,
            'java_version' => $java,
            'completed_at' => now(),
            'status_message' => null,
        ]);

        event(new InstallationCompleted($installation));
    }

    private function finalizeUninstall(ModpackInstallation $installation, LoggerInterface $logger): void
    {
        $server = $installation->server;

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
                'skip_scripts' => true,
            ]);
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
