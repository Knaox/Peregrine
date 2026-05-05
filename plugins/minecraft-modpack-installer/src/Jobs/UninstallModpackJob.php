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
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;
use Plugins\MinecraftModpackInstaller\Pelican\PelicanClient;
use Psr\Log\LoggerInterface;
use Throwable;

class UninstallModpackJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
            $installation->update([
                'status' => ModpackInstallationStatus::Failed->value,
                'status_message' => 'server_not_linked_to_pelican',
                'failed_at' => now(),
            ]);
            event(new InstallationFailed($installation, 'server_not_linked_to_pelican'));

            return;
        }

        try {
            $pelican->updateServerStartup((int) $server->pelican_server_id, [
                'egg' => $installation->pelican_egg_snapshot_id,
                'image' => $installation->pelican_image_snapshot ?? 'ghcr.io/pelican-eggs/yolks:java_17',
                'startup' => $installation->pelican_startup_snapshot
                    ?? 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                'environment' => array_replace(
                    is_array($installation->pelican_environment_snapshot)
                        ? $installation->pelican_environment_snapshot
                        : [],
                    ['SERVER_JARFILE' => $installation->pelican_jarfile_snapshot ?? 'server.jar'],
                ),
                'skip_scripts' => false,
            ]);

            $pelican->reinstallServer((int) $server->pelican_server_id);

            PollInstallStatusJob::dispatch($installation->id)->delay(now()->addSeconds(15));
        } catch (Throwable $e) {
            $logger->error('modpack: uninstall job failed', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
            $installation->update([
                'status' => ModpackInstallationStatus::Failed->value,
                'status_message' => 'uninstall_dispatch_failed: '.substr($e->getMessage(), 0, 800),
                'failed_at' => now(),
            ]);
            event(new InstallationFailed($installation, $e->getMessage()));

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
            $installation->update([
                'status' => ModpackInstallationStatus::Failed->value,
                'status_message' => 'job_failed: '.substr($e->getMessage(), 0, 800),
                'failed_at' => now(),
            ]);
            event(new InstallationFailed($installation, $e->getMessage()));
        }
    }
}
