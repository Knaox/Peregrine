<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Jobs;

use App\Services\Pelican\PelicanClientService;
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
use Plugins\MinecraftModpackInstaller\Services\JavaCompatibilityMatrix;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Single-phase uninstall flow (refactored from the previous 2-phase
 * implementation on 2026-05-08).
 *
 * Old flow (deprecated) :
 *   1. Swap server to modpack-installer egg in BB_MODPACK_OPERATION=uninstall
 *      mode → Pelican reinstall → installer script wipes /mnt/server.
 *   2. PollInstallStatusJob waits for that to finish, then PATCHes back to
 *      the user's original egg + reinstalls so Pelican repopulates from
 *      the original install script.
 *
 * New flow (this job) :
 *   1. Wipe /mnt/server directly via Pelican Client API (`wipeServerFiles`).
 *   2. PATCH the server BACK to the user's original egg in one shot,
 *      skip_scripts=false so the next reinstall actually runs.
 *   3. Trigger Pelican reinstall → original egg's install script runs on
 *      an empty directory, repopulating it as if the modpack never existed.
 *
 * Why collapse the two phases :
 *   - User-perceived UX : one `provisioning` window instead of two
 *     back-to-back, no flash of "phase 2 starting" between them.
 *   - One Pelican reinstall trigger instead of two — halves the load
 *     against Pelican's hardcoded `Limit::perMinute(5)` throttle on
 *     `/api/client/.../websocket` (separate budget but the spirit
 *     applies to all Application API mutations under load).
 *   - Less code surface : `PollInstallStatusJob::beginUninstallPhase2`
 *     and the `ModpackInstallationStatus::Reinstalling` transition are
 *     no longer entered for new uninstalls. Both kept around as dead
 *     code so any rows mid-flight at deploy time finish through the old
 *     path without crashing.
 *
 * Failure handling : a wipe failure is non-fatal (logged, we proceed
 * with PATCH + reinstall — Pelican's reinstall script will overwrite
 * any leftover files with the original egg's). A PATCH or reinstall
 * failure marks the installation Failed and triggers a snapshot
 * rollback, same as the install path.
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
        PelicanClientService $clientService,
        JavaCompatibilityMatrix $javaMatrix,
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

        // The single-phase flow REQUIRES a Pelican egg snapshot to know
        // what to PATCH back to. Without it we have no idea what the
        // user's original egg looked like, and there's no safe way to
        // recover. Fail loudly so the operator triggers a manual
        // reinstall instead of leaving the server in a broken state.
        $originalEggId = $installation->pelican_egg_snapshot_id;
        if (! is_int($originalEggId) || $originalEggId <= 0) {
            $this->markFailed($installation, 'missing_pelican_egg_snapshot');
            $this->setLocalServerStatus($server, 'provisioning_failed', $logger);

            return;
        }

        try {
            // STEP 1 — Wipe /mnt/server via the Pelican Client API.
            //
            // Non-fatal on failure : the upcoming reinstall fires the
            // original egg's install script, which (a) overwrites any
            // surviving files where it has to and (b) is idempotent
            // for the rest. Logging at warning so the operator sees
            // it but the uninstall still proceeds.
            try {
                $clientService->wipeServerFiles($server->identifier);
            } catch (Throwable $e) {
                $logger->warning('modpack: uninstall wipe failed (continuing — reinstall will overwrite)', [
                    'installation' => $installation->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // STEP 2 — Restore the original egg + image + startup +
            // environment in ONE Pelican PATCH. `skip_scripts=false` is
            // mandatory : the next `/reinstall` POST honours this flag,
            // and we want the original egg's install script to actually
            // run on the empty directory.
            //
            // The image fallback cascade matches the old phase-2 logic :
            //   1. pelican_image_snapshot — the operator's actual
            //      pre-install image, always correct when present.
            //   2. predicted_java → matrix → image — the modpack's
            //      Java requirement, safer fallback for 1.7-1.12 packs
            //      whose original egg used Java 8.
            //   3. matrix default — last resort, may still be wrong but
            //      at least it's a known-good image.
            $uninstallImage = $installation->pelican_image_snapshot
                ?? (is_int($installation->predicted_java_version)
                    ? $javaMatrix->imageForJava($installation->predicted_java_version)
                    : $javaMatrix->imageForJava($javaMatrix->defaultJava()));

            $pelican->updateServerStartup((int) $server->pelican_server_id, [
                'egg' => $originalEggId,
                'image' => $uninstallImage,
                'startup' => $installation->pelican_startup_snapshot
                    ?? 'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                // Restore the original SERVER_JARFILE — we just wiped
                // /mnt/server (or are about to via reinstall), so the
                // original egg expects its own jar name (paper.jar /
                // fabric-server-launch.jar / …), not the installer's
                // symlinked `server.jar`.
                'environment' => array_replace(
                    is_array($installation->pelican_environment_snapshot)
                        ? $installation->pelican_environment_snapshot
                        : [],
                    ['SERVER_JARFILE' => $installation->pelican_jarfile_snapshot ?? 'server.jar'],
                ),
                'skip_scripts' => false,
            ]);

            // Mirror the egg id swap into the local DB right away so
            // the SPA's sidebar / overview reflects the original egg
            // before Pelican's reinstall completes (Pelican's webhook
            // would arrive at the very end of the install ; this is
            // the "snappy UX" path that lands in <100 ms).
            $this->syncLocalEggId($server, $originalEggId, $logger);
            $this->setLocalServerStatus($server, 'provisioning', $logger);

            // STEP 3 — Trigger the reinstall. Pelican now runs the
            // ORIGINAL egg's install script on an empty /mnt/server,
            // restoring the server to its pre-modpack state.
            $pelican->reinstallServer((int) $server->pelican_server_id);

            // Hand off to the poll job for completion detection. The
            // job uses the SAME Uninstalling status as the entry point ;
            // we no longer transition to `Reinstalling` (legacy state
            // kept in the enum for backward compat with rows mid-flight
            // at deploy time, never entered for new uninstalls).
            PollInstallStatusJob::dispatch($installation->id)->delay(now()->addSeconds(15));
        } catch (Throwable $e) {
            $logger->error('modpack: uninstall failed', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
            $this->markFailed(
                $installation,
                'uninstall_dispatch_failed: '.substr($e->getMessage(), 0, 800),
            );
            $this->setLocalServerStatus($server, 'provisioning_failed', $logger);

            // Best-effort : if the PATCH partially succeeded, try a
            // second pass to nail the original egg back. The rollback
            // helper is idempotent — re-running on already-correct
            // state is a no-op.
            $this->bestEffortRollback($installation, $pelican, $javaMatrix, $logger);

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
        JavaCompatibilityMatrix $javaMatrix,
        LoggerInterface $logger,
    ): void {
        if ($installation->pelican_egg_snapshot_id === null || $installation->server === null) {
            return;
        }
        try {
            $pelican->updateServerStartup((int) $installation->server->pelican_server_id, [
                'egg' => $installation->pelican_egg_snapshot_id,
                'image' => $installation->pelican_image_snapshot
                    ?? $javaMatrix->imageForJava($javaMatrix->defaultJava()),
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
