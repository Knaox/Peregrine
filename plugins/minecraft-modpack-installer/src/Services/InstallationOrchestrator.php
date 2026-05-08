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
use Plugins\MinecraftModpackInstaller\Services\JavaCompatibilityMatrix;
use Throwable;

class InstallationOrchestrator
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ModpackProviderRegistry $registry,
        private readonly EligibilityService $eligibility,
        private readonly JavaCompatibilityMatrix $javaMatrix,
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

        // Resolve the loader & MC version up-front so the install job picks
        // the right Java image during phase 1 (no more hardcoded Java 21).
        // Both can legitimately be null when the manifest is sparse — the
        // matrix falls back to the configured default in that case.
        $loader = $this->primaryLoader($version);
        $mcVersion = $this->primaryMinecraftVersion($version);
        $predictedJava = $this->javaMatrix->requiredJava($mcVersion, $loader);

        return $this->db->transaction(function () use (
            $server,
            $actor,
            $intent,
            $summary,
            $version,
            $loader,
            $predictedJava,
        ): ModpackInstallation {
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
                    'loader' => $loader,
                    'icon_url' => $summary?->iconUrl,
                    'external_url' => $summary?->externalUrl,
                    'status' => ModpackInstallationStatus::Pending->value,
                    'status_message' => null,
                    'purge_files' => $intent->purgeFiles,
                    'java_version' => null,
                    'predicted_java_version' => $predictedJava,
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

            // Eagerly flip the server's status to `provisioning` + broadcast
            // mirror.changed BEFORE returning the API response — synchronous
            // path. This way, by the time the browser redirects to /console
            // (post installMut.onSuccess), useServer reports the new status
            // immediately and ServerDetailPage's `mergedEntries` recomputes
            // the sidebar gates (only Overview + Console while
            // status='provisioning'). Without this, the user briefly sees
            // every entry until the queue worker picks up InstallModpackJob
            // (≤ 3 s typical, longer under load), and on a slow / down
            // worker the gate would never apply at all.
            //
            // The job's own `forceFill(['status'=>'provisioning'])` step
            // (InstallModpackJob.php:167–170) is now idempotent against
            // this — `setLocalServerStatus()` early-returns when the value
            // already matches, so no double-broadcast either.
            //
            // Egg flip stays in the job because it has to land RIGHT BEFORE
            // the Pelican PATCH (matched rollback target captured there) ;
            // the local-status flip is the only step that's safe to bring
            // forward.
            if ($server->status !== 'provisioning') {
                try {
                    $server->forceFill(['status' => 'provisioning'])->save();
                    event(new \App\Events\Mirror\ServerMirrorChanged(
                        serverId: (int) $server->id,
                        resource: \App\Events\Mirror\ServerMirrorChanged::RESOURCE_SERVER,
                        action: \App\Events\Mirror\ServerMirrorChanged::ACTION_UPSERT,
                        resourceId: (int) $server->id,
                        accessUserIds: $server->accessUsers()->pluck('users.id')->all(),
                    ));
                } catch (\Throwable) {
                    // Non-fatal — the job will retry the same flip + broadcast
                    // a few hundred ms later. Better to lose the snappy UI
                    // hint than to surface a 500 to the user mid-install.
                }
            }

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

            // Same eager status flip + broadcast as startInstall : flip
            // server status synchronously so the SPA's sidebar gates
            // restrict to Overview + Console immediately after the user
            // confirms the uninstall, instead of waiting on the queue
            // worker. Idempotent with UninstallModpackJob's own flip.
            if ($server->status !== 'provisioning') {
                try {
                    $server->forceFill(['status' => 'provisioning'])->save();
                    event(new \App\Events\Mirror\ServerMirrorChanged(
                        serverId: (int) $server->id,
                        resource: \App\Events\Mirror\ServerMirrorChanged::RESOURCE_SERVER,
                        action: \App\Events\Mirror\ServerMirrorChanged::ACTION_UPSERT,
                        resourceId: (int) $server->id,
                        accessUserIds: $server->accessUsers()->pluck('users.id')->all(),
                    ));
                } catch (\Throwable) {
                    // Best-effort — the job retries the same flip ~300 ms
                    // later. See startInstall() above for reasoning.
                }
            }

            event(new UninstallationStarted($installation));

            return $installation;
        });
    }

    private function resolveSummary(ModpackProviderInterface $provider, ModpackInstallIntent $intent): ?ModpackSummary
    {
        // Direct API lookup by id — guarantees we persist the real modpack
        // name + icon + url. The previous implementation re-used the search
        // endpoint which returned wrong data for numeric ids (FTB,
        // CurseForge) where full-text search rarely matched.
        try {
            $direct = $provider->getModpack($intent->modpackId);
            if ($direct !== null) {
                return $direct;
            }
        } catch (Throwable) {
            // fall through to legacy search-based fallback below
        }

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
        } catch (Throwable) {
            // ignore, return null below
        }

        return null;
    }

    private function resolveVersion(ModpackProviderInterface $provider, ModpackInstallIntent $intent): ?ModpackVersion
    {
        // `getVersion()` is the cheap path: providers with a direct
        // fetch-by-id endpoint (CurseForge, Modrinth, FTB, …) hit it in
        // a single HTTP call. Providers without one fall back through
        // the ResolvesVersionByListing trait to the legacy
        // listVersions+filter scan, which is what we used to do
        // unconditionally. Big packs (RLCraft 80+ files) no longer push
        // PHP past max_execution_time on the install POST.
        try {
            return $provider->getVersion($intent->modpackId, $intent->versionId);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Pick a single representative loader name from the version DTO. The
     * manifest can list multiple ("forge", "fabric") for distribution
     * convenience, but for Java-image picking we need one — first match
     * is fine because the matrix evaluates loader-specific rules first
     * regardless of which loader we hand it.
     */
    private function primaryLoader(?ModpackVersion $version): ?string
    {
        if ($version === null) {
            return null;
        }
        foreach ($version->loaders as $loader) {
            if (is_string($loader) && trim($loader) !== '') {
                return strtolower(trim($loader));
            }
        }

        return null;
    }

    /**
     * Pick the highest Minecraft version listed by the manifest. Modpacks
     * commonly tag their files as compatible with a small range
     * ("1.20", "1.20.1") — picking the highest is the closest proxy to
     * "what the modpack actually targets" and is what the Java matrix
     * needs.
     */
    private function primaryMinecraftVersion(?ModpackVersion $version): ?string
    {
        if ($version === null) {
            return null;
        }
        $candidates = [];
        foreach ($version->minecraftVersions as $mc) {
            if (is_string($mc) && preg_match('/^\d+\.\d+(\.\d+)?$/', trim($mc)) === 1) {
                $candidates[] = trim($mc);
            }
        }
        if ($candidates === []) {
            return null;
        }
        usort($candidates, static fn (string $a, string $b): int => version_compare($a, $b));

        return $candidates[count($candidates) - 1];
    }
}
