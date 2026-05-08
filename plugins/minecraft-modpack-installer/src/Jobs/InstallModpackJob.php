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
use App\Models\Egg;
use App\Services\Sync\InfrastructureSync;
use Plugins\MinecraftModpackInstaller\Services\EggImporter;
use Plugins\MinecraftModpackInstaller\Services\InstallationRollbackService;
use Plugins\MinecraftModpackInstaller\Services\JavaCompatibilityMatrix;
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
        JavaCompatibilityMatrix $javaMatrix,
        InstallationRollbackService $rollback,
        LoggerInterface $logger,
    ): void {
        // Defense-in-depth against `QUEUE_CONNECTION=sync` setups: when
        // the queue runs inline in the web request, PHP's
        // `max_execution_time` (30s default for FPM, 0 in CLI) caps the
        // whole flow — Pelican PATCHes for 1.12.2 modpacks routinely
        // need more than that during the brief install_failed window.
        // Lifting the limit here makes the job survive the hop even on
        // a misconfigured queue; on a real worker (database/redis) this
        // is a no-op because the worker already calls set_time_limit
        // with `$this->timeout` (120s for this job).
        @set_time_limit($this->timeout);
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

        // Capture the local egg id BEFORE we mutate so the rollback path
        // below can restore it byte-for-byte if Pelican refuses the swap.
        $originalLocalEggId = (int) ($server->egg_id ?? 0);
        $originalLocalStatus = (string) ($server->status ?? '');

        try {
            // STEP 1 — Pelican snapshot (read-only). We need to capture the
            // server's current egg/image/startup/env BEFORE mutating
            // anything anywhere, otherwise the rollback path won't have
            // values to restore.
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

            // STEP 2 — Make sure the installer egg exists in BOTH Pelican
            // (ensureImported) and Peregrine local DB (ensureLocalMirror,
            // baked into ensureImported since the previous refactor).
            // ensureImported is mostly cached; the heavy syncEggs() only
            // fires when the local mirror is genuinely missing.
            $installerEggId = $eggImporter->ensureImported();

            // Defensive belt: even though ensureImported now populates the
            // local mirror, an out-of-band cache write or DB wipe could
            // leave it desynced. Re-check + sync if needed. Indexed lookup
            // → ~free when already in place.
            if (! Egg::where('pelican_egg_id', $installerEggId)->exists()) {
                try {
                    app(InfrastructureSync::class)->syncEggs();
                } catch (Throwable $e) {
                    $logger->warning('modpack: defensive egg mirror sync failed (continuing)', [
                        'installation' => $installation->id,
                        'pelican_egg_id' => $installerEggId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $installerLocalEggId = (int) Egg::where('pelican_egg_id', $installerEggId)->value('id');
            if ($installerLocalEggId <= 0) {
                throw new RuntimeException(
                    "installer egg #{$installerEggId} is not mirrored in Peregrine's local `eggs` table — "
                    .'aborting before touching Pelican so the panel UI cannot drift'
                );
            }

            // STEP 3 — PEREGRINE LOCAL FIRST. Flip the panel's mirror to
            // the installer egg + provisioning status BEFORE we ask Pelican
            // to do anything. Two reasons:
            //
            //   1. The shell's React queries refresh in <1 polling cycle
            //      so the user sees the egg-change visually before any
            //      potentially-slow Pelican round-trip starts. Perceived
            //      latency drops to "instant".
            //
            //   2. If Pelican's PATCH fails (network blip, 409 state
            //      conflict, …), we have an explicit rollback target —
            //      `$originalLocalEggId` / `$originalLocalStatus` —
            //      captured above. The catch block restores them.
            //
            // This is the explicit ordering the operator requested:
            // "tu ne lance pas le changement d'egg et l'installation
            // dans pelican tant que ce n'est pas fait dans peregrine".
            try {
                $server->forceFill([
                    'egg_id' => $installerLocalEggId,
                    'status' => 'provisioning',
                ])->save();
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'local egg/status flip failed before Pelican PATCH — refusing to start install '
                    .'so Peregrine and Pelican can never desync. Cause: '.$e->getMessage(),
                    previous: $e,
                );
            }

            // Broadcast the local change so the React shell's
            // `useServerLiveUpdates` hook re-runs the sidebar gate
            // immediately (without a full reload).
            try {
                event(new \App\Events\Mirror\ServerMirrorChanged(
                    serverId: (int) $server->id,
                    resource: \App\Events\Mirror\ServerMirrorChanged::RESOURCE_SERVER,
                    action: \App\Events\Mirror\ServerMirrorChanged::ACTION_UPSERT,
                    resourceId: (int) $server->id,
                    accessUserIds: $server->accessUsers()->pluck('users.id')->all(),
                ));
            } catch (Throwable) {
                // best effort; the periodic polling will eventually
                // surface the change anyway.
            }

            // STEP 4 — Pelican PATCH (now safe to do). The installer egg
            // env is tight on purpose: anything else would be stripped
            // by Pelican before hitting the installer container.
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

            // Phase 1 image: Java major matching the modpack (predicted by
            // JavaCompatibilityMatrix from mc_version + loader at
            // startInstall). Falls back to the configured default when the
            // orchestrator couldn't predict a value (manifest with no
            // metadata) — never silently to Java 21, which used to break
            // Forge 1.7.10 / 1.12.2 packs whose installer refuses Java 21.
            $predictedJava = is_int($installation->predicted_java_version)
                ? $installation->predicted_java_version
                : $javaMatrix->defaultJava();
            $installerImage = $javaMatrix->imageForJava($predictedJava);

            $logger->info('modpack: install starting — Pelican PATCH', [
                'installation' => $installation->id,
                'predicted_java' => $predictedJava,
                'installer_image' => $installerImage,
                'installer_egg' => $installerEggId,
            ]);

            $updateResponse = $pelican->updateServerStartup((int) $server->pelican_server_id, [
                'egg' => $installerEggId,
                'image' => $installerImage,
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

            // STEP 5 — Trigger the reinstall on Pelican. The install
            // container will run our egg's bash script.
            $pelican->reinstallServer((int) $server->pelican_server_id);

            PollInstallStatusJob::dispatch($installation->id)->delay(now()->addSeconds(15));
        } catch (Throwable $e) {
            $logger->error('modpack: install job failed', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);

            // Roll back the Peregrine-local mutation from STEP 3 first —
            // we did it before touching Pelican, so even if the failure
            // happened before the Pelican PATCH, the local DB still
            // needs to be restored. Idempotent if the value never changed.
            if ($originalLocalEggId > 0) {
                try {
                    $server->forceFill([
                        'egg_id' => $originalLocalEggId,
                        'status' => $originalLocalStatus !== '' ? $originalLocalStatus : 'active',
                    ])->save();
                } catch (Throwable $rollbackError) {
                    $logger->warning('modpack: local egg/status rollback failed', [
                        'installation' => $installation->id,
                        'error' => $rollbackError->getMessage(),
                    ]);
                }
            }

            $this->markFailed($installation, 'install_dispatch_failed: '.$e->getMessage());
            if ($server !== null) {
                $this->setLocalServerStatus($server, 'provisioning_failed', $logger);
            }
            // The rollback service handles the Pelican side (egg PATCH
            // back to snapshot, image restored, etc.) when STEP 4 made
            // it that far. It's a no-op when no snapshot was captured.
            $rollback->rollbackToSnapshot($installation, $logger);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        // Laravel calls this when the queue runner aborts the job (OOM,
        // worker killed, deploy restart, …). The handle()-level catch
        // doesn't fire in that path so without an explicit rollback the
        // server stays pinned to the modpack-installer egg in Pelican
        // forever — operator clicks Start, gets the install container,
        // bash never returns. Resolve the rollback service from the
        // container (queue-failed callbacks don't get DI for free) and
        // hand off to the same code path every other failure uses.
        $installation = ModpackInstallation::with('server')->find($this->installationId);
        if ($installation === null) {
            return;
        }

        if ($installation->status === ModpackInstallationStatus::Installing
            || $installation->status === ModpackInstallationStatus::Pending) {
            $reason = 'job_failed: '.substr($e->getMessage(), 0, 800);
            $rollback = InstallationRollbackService::fromContainer();
            $rollback->failAndRollback($installation, $reason);

            // Force the server-status mirror to provisioning_failed in
            // addition to the rollback's own active-state reset. The
            // operator should see the failure indicator on the server
            // card; a quiet "active" without context would mask the
            // problem.
            if ($installation->server !== null) {
                try {
                    $installation->server->forceFill(['status' => 'provisioning_failed'])->save();
                } catch (Throwable) {
                    // ignore — best effort, the rollback already touched
                    // the row and any further error is non-fatal here.
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

    // Rollback used to live here as a private method but is now owned by
    // InstallationRollbackService — every failure path (handle() catch,
    // failed() queue callback, poll-job timeout, reconcile cron) goes
    // through the same code path. JavaCompatibilityMatrix is still
    // injected into handle() because it's needed for the install-phase
    // image picking (lines 122-123); only the rollback dependency moved.

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
