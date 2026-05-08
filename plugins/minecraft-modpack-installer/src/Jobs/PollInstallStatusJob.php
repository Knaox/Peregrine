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
use Plugins\MinecraftModpackInstaller\Services\InstallationRollbackService;
use Plugins\MinecraftModpackInstaller\Services\JavaCompatibilityMatrix;
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
        JavaCompatibilityMatrix $javaMatrix,
        EggImporter $eggImporter,
        InstallationRollbackService $rollback,
        LoggerInterface $logger,
    ): void {
        // Defense-in-depth against `QUEUE_CONNECTION=sync`: when the
        // poll job re-fires itself synchronously inside the previous
        // job's HTTP request (which is what `sync` does), PHP's
        // 30s default `max_execution_time` clips the swap-back PATCH
        // mid-call — the user sees a 500 and the egg never restores.
        // Lifting the limit here matches the worker-mode behaviour.
        @set_time_limit($this->timeout);
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
            // Roll back to the user's original egg only when we still
            // own the installer egg — the install path. New uninstalls
            // are already on the original egg by the time we poll
            // (UninstallModpackJob PATCHes back BEFORE triggering
            // reinstall), so a rollback would be a no-op or worse.
            // The rollback service is idempotent, so re-running on a
            // flapping install server is still safe.
            if ($isInstall) {
                $rollback->rollbackToSnapshot($installation, $logger);
            }

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
                // New 1-phase uninstall : the failure is on the original
                // egg's install script running on the empty directory.
                // Rollback is NOT helpful — we're already on the
                // original egg, just the script crashed. Operator can
                // retry via the panel reinstall button.
                $isUninstall => 'uninstall_reinstall_failed',
                // Legacy 2-phase path. Never entered by new uninstalls.
                $isReinstall => 'uninstall_reinstall_failed',
                default => 'unknown_phase_failed',
            };
            $this->markFailed($installation, $reason);
            $this->setLocalServerStatus($server, 'provisioning_failed', $logger);
            // Rollback only when we still own the installer egg — the
            // install path. New uninstalls have already swapped to the
            // user's original egg in `UninstallModpackJob` BEFORE the
            // reinstall fires, so a rollback here would be a no-op
            // (or worse, replay an old snapshot over fresh state).
            if ($isInstall) {
                $rollback->rollbackToSnapshot($installation, $logger);
            }

            return;
        }

        if ($isInstall) {
            $this->finalizeInstall($installation, $pelican, $eggImporter, $javaDetection, $javaMatrix, $logger);
        } else {
            // Both `Uninstalling` and `Reinstalling` finalize the same
            // way now. New uninstalls land on `Uninstalling` and skip
            // straight here once the single Pelican reinstall completes
            // (refactor 2026-05-08 — collapsed 2-phase uninstall to 1).
            // The `Reinstalling` enum case is preserved as a backward-
            // compat path so any rows that were mid-flight in phase 2
            // at deploy time still finalize cleanly. New rows never
            // transition into `Reinstalling`.
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
        JavaCompatibilityMatrix $javaMatrix,
        LoggerInterface $logger,
    ): void {
        $server = $installation->server;
        $serverId = (int) $server->pelican_server_id;

        // ────────────────────────────────────────────────────────────
        // Pick the Java major to swap the runtime image to.
        // Three signals, cascading in order of confidence:
        //   1. MCJars (jar SHA-256 → upstream Java) — definitive when
        //      it's a vanilla / Paper / Purpur jar.
        //   2. predicted_java_version — computed at orchestrator time
        //      from manifest's MC version + loader. Always available
        //      for modpacks (never null after the recent
        //      InstallationOrchestrator refactor).
        //   3. Matrix default (config / admin override / 17).
        //
        // Critical change: detect() now returns ?int. A null means
        // "MCJars couldn't identify the jar" (typical for Forge-launcher
        // jars with mods baked in — RLCraft 1.12.2 hits this every time)
        // — and we MUST fall through to the predicted Java instead of
        // pretending MCJars said 17. Before this distinction, every
        // modded jar got pinned to Java 17 even when the modpack was
        // 1.12.2 (Java 8) and the original egg used Java 8 — Pelican
        // accepted the wrong image silently and the server crashed at
        // startup with classfile-version mismatch.
        // ────────────────────────────────────────────────────────────
        $detected = null;
        try {
            $detected = $javaDetection->detect($server, 'server.jar');
        } catch (Throwable $e) {
            $logger->info('modpack: java detection threw, falling through to predicted', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
        }

        $predicted = is_int($installation->predicted_java_version)
            ? $installation->predicted_java_version
            : null;

        $java = $detected
            ?? $predicted
            ?? $javaMatrix->defaultJava();

        $logger->info('modpack: post-install image picking', [
            'installation' => $installation->id,
            'mcjars_detected' => $detected,        // ?int — null when MCJars couldn't ID the jar
            'predicted_java' => $predicted,         // ?int — from manifest mc+loader
            'matrix_default' => $javaMatrix->defaultJava(),
            'chosen_java' => $java,
            'chosen_image' => $javaMatrix->imageForJava($java),
        ]);

        // Scrub BB_MODPACK_* values before the egg swap. While we're still on
        // the installer egg these keys can be safely overwritten with their
        // permissive defaults (provider→modrinth, ids→'_', purge→0, op→
        // install). After the swap, even though Pelican filters env by
        // current egg when sending to Wings, the server_variables rows
        // still surface in admin UIs and panel debug tools.
        try {
            // Image during scrub matches the installer egg's runtime — same
            // major as the predicted Java for this modpack (or default Java
            // when no prediction is available). The scrub itself only
            // mutates env vars; the image is forwarded so Pelican's PATCH
            // doesn't blank out the field.
            $scrubJava = is_int($installation->predicted_java_version)
                ? $installation->predicted_java_version
                : $javaMatrix->defaultJava();
            $pelican->scrubInstallerEnvironment(
                $serverId,
                $eggImporter->ensureImported(),
                'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                $javaMatrix->imageForJava($scrubJava),
            );
        } catch (Throwable $e) {
            $logger->warning('modpack: BB_MODPACK_* scrub failed (continuing)', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
        }

        $swapBackPayload = [
            'egg' => $installation->pelican_egg_snapshot_id,
            'image' => $javaMatrix->imageForJava($java),
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
            // PATCH (only POST /settings/reinstall does), so the flag has
            // no benefit here, and it persists on the server row — every
            // subsequent native /reinstall would then be silently skipped
            // (Pelican fires Server\Installed instantly without running
            // the egg's install script).
            'skip_scripts' => false,
        ];

        // Pelican's StartupModificationService refuses PATCH `/startup`
        // with HTTP 409 `ServerStateConflictException` while the server
        // is mid-transition. Right after the install finishes, Pelican
        // momentarily holds the server in a transitional state before
        // accepting external mutations — observed in production as
        // "swap-back failed for big modpacks" (RLCraft 1.12.2: install
        // takes ~3-5 minutes, Pelican's lock window is wider than the
        // typical sub-1s pack). Retry on 409 with backoff so the
        // dynamic-image swap-back actually lands.
        $patched = false;
        $lastError = null;
        foreach ([0, 3, 8] as $delaySeconds) {
            if ($delaySeconds > 0) {
                $logger->info('modpack: retrying swap-back PATCH after Pelican state conflict', [
                    'installation' => $installation->id,
                    'delay_seconds' => $delaySeconds,
                ]);
                sleep($delaySeconds);
            }
            try {
                $pelican->updateServerStartup($serverId, $swapBackPayload);
                $patched = true;
                break;
            } catch (Throwable $e) {
                $lastError = $e;
                $body = method_exists($e, 'response') ? (string) $e->response()?->body() : '';
                $isStateConflict = str_contains($e->getMessage(), '409')
                    || str_contains($body, 'ServerStateConflictException');
                $logger->warning('modpack: swap-back PATCH attempt failed', [
                    'installation' => $installation->id,
                    'attempt_delay' => $delaySeconds,
                    'state_conflict' => $isStateConflict,
                    'error' => $e->getMessage(),
                    'body' => substr($body, 0, 400),
                ]);
                // Non-409 errors (auth, network, validation…) won't be
                // resolved by another sleep — bail to the failure path.
                if (! $isStateConflict) {
                    break;
                }
            }
        }

        if (! $patched) {
            $logger->error('modpack: swap-back failed after retries', [
                'installation' => $installation->id,
                'error' => $lastError?->getMessage(),
            ]);
            $this->markFailed(
                $installation,
                'swap_back_failed: '.($lastError?->getMessage() ?? 'unknown'),
            );
            $this->setLocalServerStatus($server, 'provisioning_failed', $logger);

            return;
        }

        $logger->info('modpack: swap-back PATCH succeeded', [
            'installation' => $installation->id,
            'restored_egg' => $installation->pelican_egg_snapshot_id,
            'restored_image' => $swapBackPayload['image'],
        ]);

        // Mirror the egg swap into Peregrine's local DB right away — fast UI
        // path. The Server\Installed webhook may or may not fire depending
        // on the skip_scripts state, and even when it does the round-trip
        // through Reverb takes a beat; clearing the spinner instantly here
        // is the difference between a snappy UX and a phantom "still
        // installing" indicator.
        $this->syncLocalEggId(
            $server,
            (int) $installation->pelican_egg_snapshot_id,
            $logger,
        );
        $this->setLocalServerStatus($server, 'active', $logger);

        // Defensive belt-and-suspenders : Pelican fires `updated:Server`
        // automatically and Peregrine's webhook listener will sync the local
        // mirror on its own — but if the webhook is disabled (or Reverb is
        // down on a particular host), the local egg_id stays stale and the UI
        // shows the modpack-installer egg until next reconciler tick. Dispatch
        // the sync job manually so we own the freshness regardless. This
        // overlaps with the syncLocalEggId() above by design — they pull
        // from different sources (snapshot vs live Pelican) and a stale
        // snapshot can't bite us thanks to this.
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
        JavaCompatibilityMatrix $javaMatrix,
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
            $scrubJava = is_int($installation->predicted_java_version)
                ? $installation->predicted_java_version
                : $javaMatrix->defaultJava();
            $pelican->scrubInstallerEnvironment(
                (int) $server->pelican_server_id,
                $eggImporter->ensureImported(),
                'java -Xms128M -Xmx{{SERVER_MEMORY}}M -jar {{SERVER_JARFILE}}',
                $javaMatrix->imageForJava($scrubJava),
            );
        } catch (Throwable $e) {
            $logger->warning('modpack: BB_MODPACK_* scrub failed (continuing)', [
                'installation' => $installation->id, 'error' => $e->getMessage(),
            ]);
        }

        // Image picking on uninstall phase 2 mirrors the install
        // finalize cascade so a missing snapshot doesn't pin the
        // server back on the wrong Java. Order:
        //   1. pelican_image_snapshot — the operator's actual pre-install
        //      image; always correct when present.
        //   2. predicted_java_version → matrix → image — the modpack's
        //      Java requirement; safer fallback than `defaultJava()` for
        //      a 1.7-1.12 server whose original egg also wanted Java 8.
        //   3. Matrix default — last resort, may still be wrong but at
        //      least it's a valid known-good image.
        $uninstallImage = $installation->pelican_image_snapshot
            ?? (is_int($installation->predicted_java_version)
                ? $javaMatrix->imageForJava($installation->predicted_java_version)
                : $javaMatrix->imageForJava($javaMatrix->defaultJava()));

        $logger->info('modpack: uninstall phase 2 image picking', [
            'installation' => $installation->id,
            'snapshot_image' => $installation->pelican_image_snapshot,
            'predicted_java' => $installation->predicted_java_version,
            'chosen_image' => $uninstallImage,
        ]);

        try {
            $pelican->updateServerStartup((int) $server->pelican_server_id, [
                'egg' => $installation->pelican_egg_snapshot_id,
                'image' => $uninstallImage,
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

    // Rollback used to live here as a private method but is now owned by
    // InstallationRollbackService — every failure path (poll, queue
    // failure handler, reconcile cron) goes through the same code path,
    // so a regression in one site can't leave others on the installer
    // egg.
}
