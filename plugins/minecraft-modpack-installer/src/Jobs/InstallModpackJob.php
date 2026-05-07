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
use Plugins\MinecraftModpackInstaller\Services\ModpackSettingsService;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class InstallModpackJob implements ShouldQueue
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
        return "modpack-install:{$this->installationId}";
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(
        PelicanClient $pelican,
        EggImporter $eggImporter,
        ModpackSettingsService $settings,
        LoggerInterface $logger,
    ): void {
        $installation = ModpackInstallation::with('server')->find($this->installationId);
        if ($installation === null) {
            return;
        }
        if ($installation->status !== ModpackInstallationStatus::Pending) {
            $logger->info('modpack: install job skipped (status moved past pending)', [
                'installation' => $installation->id,
                'status' => $installation->status->value,
            ]);

            return;
        }

        $server = $installation->server;
        if ($server === null || $server->pelican_server_id === null) {
            $this->markFailed($installation, 'server_not_linked_to_pelican');

            return;
        }

        try {
            $raw = $pelican->getServerRaw((int) $server->pelican_server_id);
            $attributes = $raw['attributes'] ?? $raw;
            $container = $attributes['container'] ?? [];
            $environment = is_array($container['environment'] ?? null) ? $container['environment'] : [];

            $jarfile = isset($environment['SERVER_JARFILE']) ? (string) $environment['SERVER_JARFILE'] : 'server.jar';

            $installation->update([
                'status' => ModpackInstallationStatus::Installing->value,
                'started_at' => now(),
                'pelican_egg_snapshot_id' => (int) ($attributes['egg'] ?? 0) ?: null,
                'pelican_image_snapshot' => isset($container['image']) ? (string) $container['image'] : null,
                'pelican_startup_snapshot' => isset($container['startup_command'])
                    ? (string) $container['startup_command']
                    : null,
                'pelican_jarfile_snapshot' => $jarfile,
                'pelican_environment_snapshot' => $environment,
            ]);

            $installerEggId = $eggImporter->ensureImported();

            // The egg only declares the BB_MODPACK_* variables, so
            // anything else listed here would be stripped by Pelican before
            // hitting the installer container. Keep the wire surface tight.
            $installerEnvironment = [
                'BB_MODPACK_PROVIDER' => $installation->provider->value,
                'BB_MODPACK_ID' => $installation->modpack_id,
                'BB_MODPACK_VERSION_ID' => $installation->version_id,
                'BB_MODPACK_GAME_VERSION' => $this->extractMinecraftVersion($installation),
                'BB_MODPACK_PURGE' => $installation->purge_files ? '1' : '0',
                'BB_MODPACK_CURSEFORGE_KEY' => (string) ($settings->curseforgeApiKey() ?? ''),
                'BB_MODPACK_OPERATION' => 'install',
                'SERVER_JARFILE' => 'server.jar',
            ];

            $updateResponse = $pelican->updateServerStartup((int) $server->pelican_server_id, [
                'egg' => $installerEggId,
                'image' => 'ghcr.io/pelican-eggs/yolks:java_21',
                'startup' => 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                'environment' => $installerEnvironment,
                'skip_scripts' => false,
            ]);

            // Confirm Pelican accepted the egg swap before kicking off the
            // reinstall — silent ignores would otherwise install the modpack
            // on the previous egg and leave the panel out of sync.
            $confirmedEggId = $this->confirmedEggId($updateResponse, $pelican, (int) $server->pelican_server_id);
            if ($confirmedEggId !== $installerEggId) {
                throw new RuntimeException(
                    "egg_swap_unconfirmed: expected {$installerEggId} got "
                    .($confirmedEggId === null ? 'null' : (string) $confirmedEggId)
                );
            }

            // Mirror the swap into the local servers.egg_id immediately —
            // Pelican will fire the Server\Installed webhook on completion,
            // but we want the panel UI to reflect the installer egg during
            // the install, not the previous one. Also flip local status
            // so the panel UI shows a "provisioning" indicator on the
            // server row throughout the modpack install.
            $this->syncLocalEggId($server, $confirmedEggId, $logger);
            $this->setLocalServerStatus($server, 'provisioning', $logger);

            $pelican->reinstallServer((int) $server->pelican_server_id);

            PollInstallStatusJob::dispatch($installation->id)->delay(now()->addSeconds(15));
        } catch (Throwable $e) {
            $logger->error('modpack: install job failed', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
            $this->markFailed($installation, 'install_dispatch_failed: '.$e->getMessage());
            if ($server !== null) {
                $this->setLocalServerStatus($server, 'provisioning_failed', $logger);
            }
            $this->bestEffortRollback($installation, $pelican, $logger);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $installation = ModpackInstallation::with('server')->find($this->installationId);
        if ($installation === null) {
            return;
        }
        if ($installation->status === ModpackInstallationStatus::Installing
            || $installation->status === ModpackInstallationStatus::Pending) {
            $this->markFailed($installation, 'job_failed: '.substr($e->getMessage(), 0, 800));
            if ($installation->server !== null) {
                // Best-effort: leave a clean slate so the panel UI doesn't
                // show a permanent spinner if the queue worker died mid-job.
                try {
                    $installation->server->forceFill(['status' => 'provisioning_failed'])->save();
                } catch (\Throwable) {
                    // ignore — Concerns trait isn't loaded here
                }
            }
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
                // skip_scripts must stay false: PATCH /startup never runs an
                // install on its own, so the flag has no effect during
                // rollback — but Pelican stores it on the server row and a
                // persisted true would silently skip every future native
                // reinstall. Mirror the egg id locally instead so the panel
                // still reflects the right egg post-rollback.
                'skip_scripts' => false,
            ]);

            $this->syncLocalEggId(
                $installation->server,
                (int) $installation->pelican_egg_snapshot_id,
                $logger,
            );
        } catch (Throwable $e) {
            $logger->warning('modpack: rollback after install failure failed', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractMinecraftVersion(ModpackInstallation $installation): string
    {
        $label = (string) ($installation->version_label ?? '');
        if (preg_match('/(\d+\.\d+(\.\d+)?)/', $label, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Extract the egg id Pelican confirmed for the swap. Tries the PATCH
     * response first (newer Pelican versions return the resource), then
     * falls back to a fresh GET — covers 204 No Content and lets us
     * detect silent ignores.
     *
     * @param  array<string, mixed>  $patchResponse
     */
    private function confirmedEggId(array $patchResponse, PelicanClient $pelican, int $pelicanServerId): ?int
    {
        $candidate = $patchResponse['attributes']['egg']
            ?? $patchResponse['data']['attributes']['egg']
            ?? null;

        if (is_int($candidate) || (is_string($candidate) && ctype_digit($candidate))) {
            return (int) $candidate;
        }

        try {
            $raw = $pelican->getServerRaw($pelicanServerId);
            $eggId = $raw['attributes']['egg'] ?? null;

            return is_int($eggId) || (is_string($eggId) && ctype_digit($eggId))
                ? (int) $eggId
                : null;
        } catch (Throwable) {
            return null;
        }
    }
}
