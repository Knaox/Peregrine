<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services;

use App\Models\Server;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Plugins\MinecraftModpackInstaller\Enums\ModpackInstallationStatus;
use Plugins\MinecraftModpackInstaller\Events\InstallationStarted;
use Plugins\MinecraftModpackInstaller\Events\UninstallationStarted;
use Plugins\MinecraftModpackInstaller\Exceptions\InstallationConflictException;
use Plugins\MinecraftModpackInstaller\Exceptions\ProviderNotConfiguredException;
use Plugins\MinecraftModpackInstaller\Exceptions\ServerNotEligibleException;
use Plugins\MinecraftModpackInstaller\Jobs\InstallModpackJob;
use Plugins\MinecraftModpackInstaller\Jobs\UninstallModpackJob;
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackSummary;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackVersion;
use Plugins\MinecraftModpackInstaller\Services\DTO\SearchCriteria;
use Plugins\MinecraftModpackInstaller\Services\Providers\Contracts\ModpackProviderInterface;
use Throwable;

class InstallationOrchestrator
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ModpackProviderRegistry $registry,
        private readonly EligibilityService $eligibility,
    ) {}

    public function startInstall(Server $server, User $actor, ModpackInstallIntent $intent): ModpackInstallation
    {
        if (! $this->eligibility->isEligible($server)) {
            throw new ServerNotEligibleException();
        }

        $provider = $this->registry->get($intent->provider);
        if (! $provider->isConfigured()) {
            throw new ProviderNotConfiguredException($intent->provider);
        }

        $summary = $this->resolveSummary($provider, $intent);
        $version = $this->resolveVersion($provider, $intent);

        return $this->db->transaction(function () use ($server, $actor, $intent, $summary, $version): ModpackInstallation {
            $existing = ModpackInstallation::where('server_id', $server->id)->lockForUpdate()->first();
            if ($existing !== null && $existing->status->isActive()) {
                throw new InstallationConflictException();
            }

            $installation = ModpackInstallation::updateOrCreate(
                ['server_id' => $server->id],
                [
                    'provider' => $intent->provider->value,
                    'modpack_id' => $intent->modpackId,
                    'modpack_name' => $summary?->name ?? $intent->modpackId,
                    'modpack_slug' => $summary?->slug,
                    'version_id' => $intent->versionId,
                    'version_label' => $version?->label,
                    'icon_url' => $summary?->iconUrl,
                    'external_url' => $summary?->externalUrl,
                    'status' => ModpackInstallationStatus::Pending->value,
                    'status_message' => null,
                    'purge_files' => $intent->purgeFiles,
                    'java_version' => null,
                    'pelican_egg_snapshot_id' => null,
                    'pelican_image_snapshot' => null,
                    'pelican_startup_snapshot' => null,
                    'pelican_jarfile_snapshot' => null,
                    'pelican_environment_snapshot' => null,
                    'started_at' => now(),
                    'completed_at' => null,
                    'failed_at' => null,
                    'installed_by' => $actor->id,
                ],
            );

            InstallModpackJob::dispatch($installation->id);

            event(new InstallationStarted($installation));

            return $installation;
        });
    }

    public function startUninstall(Server $server): ModpackInstallation
    {
        return $this->db->transaction(function () use ($server): ModpackInstallation {
            $installation = ModpackInstallation::where('server_id', $server->id)->lockForUpdate()->first();
            if ($installation === null) {
                throw new InstallationConflictException('No modpack is currently installed on this server.');
            }
            if ($installation->status !== ModpackInstallationStatus::Completed) {
                throw new InstallationConflictException();
            }

            $installation->update([
                'status' => ModpackInstallationStatus::Uninstalling->value,
                'status_message' => null,
                'started_at' => now(),
                'completed_at' => null,
                'failed_at' => null,
            ]);

            UninstallModpackJob::dispatch($installation->id);

            event(new UninstallationStarted($installation));

            return $installation;
        });
    }

    private function resolveSummary(ModpackProviderInterface $provider, ModpackInstallIntent $intent): ?ModpackSummary
    {
        try {
            $criteria = new SearchCriteria(
                query: $intent->modpackId,
                minecraftVersion: null,
                loader: null,
                page: 1,
                pageSize: 12,
            );
            $result = $provider->search($criteria);
            foreach ($result->hits as $hit) {
                if ($hit->modpackId === $intent->modpackId) {
                    return $hit;
                }
            }

            return $result->hits[0] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveVersion(ModpackProviderInterface $provider, ModpackInstallIntent $intent): ?ModpackVersion
    {
        try {
            $versions = $provider->listVersions($intent->modpackId, null);
            foreach ($versions as $version) {
                if ($version->versionId === $intent->versionId) {
                    return $version;
                }
            }
        } catch (Throwable) {
            // ignore
        }

        return null;
    }
}
