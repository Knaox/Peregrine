<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services;

use App\Events\Mirror\ServerMirrorChanged;
use App\Models\Server;
use App\Services\Sync\EggResolver;
use Illuminate\Container\Container;
use Plugins\MinecraftModpackInstaller\Enums\ModpackInstallationStatus;
use Plugins\MinecraftModpackInstaller\Events\InstallationFailed;
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;
use Plugins\MinecraftModpackInstaller\Pelican\PelicanClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Centralised rollback for any modpack-install path that fails.
 *
 * The egg-swap dance the installer performs has THREE places where the
 * server can end up stranded on the modpack-installer egg if we don't
 * rollback ourselves:
 *
 *  1. PollInstallStatusJob picks up `install_failed` from Pelican — we
 *     already rolled back here, but only on this exact status.
 *  2. PollInstallStatusJob hits the operator-configured timeout — we
 *     marked the installation failed but left Pelican on the installer
 *     egg, so the next user start would boot into the *installer* egg's
 *     image and fail again.
 *  3. InstallModpackJob is killed by the queue runner (OOM, deploy
 *     restart, …) — Laravel calls `failed()`, we marked the row but
 *     never rolled back; the cron-based reconciler later flips the row
 *     to `failed` but also doesn't touch Pelican.
 *
 * This service owns the rollback so every failure path can ask for
 * "restore this server to whatever egg/image/startup it had before the
 * modpack flow started" with one call. The result is that a failed
 * install always ends with the server on the operator's original egg,
 * both in Pelican and in Peregrine's local mirror.
 */
class InstallationRollbackService
{
    public function __construct(
        private readonly PelicanClient $pelican,
        private readonly JavaCompatibilityMatrix $javaMatrix,
    ) {}

    /**
     * Best-effort rollback: re-applies the snapshot egg/image/startup we
     * captured at install start, mirrors the egg id locally, and resets
     * `servers.status` so the UI no longer shows a phantom spinner.
     *
     * Idempotent: callers can fire it multiple times (the `markFailed`
     * + status-set parts no-op when already in the target state).
     *
     * Returns true when the Pelican PATCH succeeded, false otherwise.
     * Failures are logged and swallowed so the caller is never blocked
     * from completing its own bookkeeping.
     */
    public function rollbackToSnapshot(
        ModpackInstallation $installation,
        ?LoggerInterface $logger = null,
    ): bool {
        $logger ??= new NullLogger();

        $server = $installation->server;
        if ($server === null || $server->pelican_server_id === null) {
            $logger->info('modpack: rollback skipped — server not linked to Pelican', [
                'installation' => $installation->id,
            ]);

            return false;
        }
        if ($installation->pelican_egg_snapshot_id === null) {
            $logger->info('modpack: rollback skipped — no egg snapshot captured', [
                'installation' => $installation->id,
            ]);

            return false;
        }

        $serverId = (int) $server->pelican_server_id;
        $snapshotEggId = (int) $installation->pelican_egg_snapshot_id;

        // Same image-picking cascade as finalizeInstall: prefer the
        // operator's pre-install image, then fall back to the modpack's
        // predicted Java image, then the matrix default. Without this
        // we'd pin every silent rollback to the matrix default Java
        // (typically 17), which breaks 1.7-1.16 servers when the
        // snapshot is missing.
        $rollbackImage = $installation->pelican_image_snapshot
            ?? (is_int($installation->predicted_java_version)
                ? $this->javaMatrix->imageForJava($installation->predicted_java_version)
                : $this->javaMatrix->imageForJava($this->javaMatrix->defaultJava()));

        $payload = [
            'egg' => $snapshotEggId,
            'image' => $rollbackImage,
            'startup' => $installation->pelican_startup_snapshot
                ?? 'java -jar {{SERVER_JARFILE}}',
            'environment' => $installation->pelican_environment_snapshot
                ?? ['SERVER_JARFILE' => $installation->pelican_jarfile_snapshot ?? 'server.jar'],
            // Same caveat as finalizeInstall: skip_scripts MUST stay
            // false. Pelican's StartupModificationService never triggers
            // an install on PATCH, but it persists the flag on the server
            // row, and a true value would silently skip every later
            // native /reinstall by the operator.
            'skip_scripts' => false,
        ];

        // Retry on Pelican's transient `ServerStateConflictException`.
        // Same rationale as `finalizeInstall`: right after a failed
        // install, Pelican holds the server in a transitional state
        // for a few seconds and refuses PATCH /startup. Without this
        // retry the rollback fires before Pelican is ready, gets a
        // single 409, and leaves the server stuck on the modpack-
        // installer egg even though we marked the installation failed.
        $patched = false;
        $lastError = null;
        foreach ([0, 3, 8] as $delaySeconds) {
            if ($delaySeconds > 0) {
                $logger->info('modpack: rollback PATCH retrying after state conflict', [
                    'installation' => $installation->id,
                    'delay_seconds' => $delaySeconds,
                ]);
                sleep($delaySeconds);
            }
            try {
                $this->pelican->updateServerStartup($serverId, $payload);
                $patched = true;
                break;
            } catch (Throwable $e) {
                $lastError = $e;
                $body = method_exists($e, 'response') ? (string) $e->response()?->body() : '';
                $isStateConflict = str_contains($e->getMessage(), '409')
                    || str_contains($body, 'ServerStateConflictException');
                $logger->warning('modpack: rollback PATCH attempt failed', [
                    'installation' => $installation->id,
                    'attempt_delay' => $delaySeconds,
                    'state_conflict' => $isStateConflict,
                    'error' => $e->getMessage(),
                    'body' => substr($body, 0, 400),
                ]);
                if (! $isStateConflict) {
                    break;
                }
            }
        }

        if (! $patched) {
            $logger->warning('modpack: Pelican rollback PATCH failed after retries', [
                'installation' => $installation->id,
                'pelican_server_id' => $serverId,
                'snapshot_egg_id' => $snapshotEggId,
                'error' => $lastError?->getMessage(),
            ]);

            return false;
        }

        $this->syncLocalEggId($server, $snapshotEggId, $logger);

        // Surface the failed state in the React shell, then clear it back
        // to active so the next start attempt isn't gated on a phantom
        // "still provisioning" indicator. We deliberately go to `active`
        // rather than `provisioning_failed` here because the rollback IS
        // the recovery — the server is healthy on its original egg now,
        // and any further error will surface on its own start cycle.
        $this->setLocalServerStatus($server, 'active', $logger);

        $logger->info('modpack: rollback completed', [
            'installation' => $installation->id,
            'pelican_server_id' => $serverId,
            'restored_egg_id' => $snapshotEggId,
        ]);

        return true;
    }

    /**
     * Convenience wrapper: mark the installation failed (with the given
     * reason), fire the InstallationFailed event, then rollback Pelican
     * + Peregrine. Used by every error path that needs the full failure
     * bookkeeping in one shot (queue `failed()` callbacks, reconcile cron,
     * timeout watcher, …).
     */
    public function failAndRollback(
        ModpackInstallation $installation,
        string $reason,
        ?LoggerInterface $logger = null,
    ): void {
        $logger ??= new NullLogger();

        // Avoid duplicate state writes when this is called by a path that
        // already flipped the row (e.g. ReconcileStaleInstallations
        // bulk-updates first, then calls us per-installation for the
        // rollback step). The event still fires only once because we
        // guard on the prior status.
        if ($installation->status !== ModpackInstallationStatus::Failed) {
            try {
                $installation->forceFill([
                    'status' => ModpackInstallationStatus::Failed->value,
                    'status_message' => $reason,
                    'failed_at' => now(),
                ])->save();
                event(new InstallationFailed($installation, $reason));
            } catch (Throwable $e) {
                $logger->warning('modpack: markFailed write failed', [
                    'installation' => $installation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->rollbackToSnapshot($installation, $logger);
    }

    /**
     * Resolve the service from the container. Static helper for callers
     * that aren't in the DI graph (Laravel queue `failed()` callbacks
     * receive a Throwable but no service deps).
     */
    public static function fromContainer(): self
    {
        /** @var self $service */
        $service = Container::getInstance()->make(self::class);

        return $service;
    }

    private function syncLocalEggId(Server $server, int $pelicanEggId, LoggerInterface $logger): void
    {
        if ($pelicanEggId <= 0) {
            return;
        }

        try {
            $localEggId = EggResolver::resolveLocalEggId(
                ['egg_id' => $pelicanEggId],
                (int) ($server->pelican_server_id ?? 0),
            );
        } catch (Throwable $e) {
            $logger->warning('modpack: rollback local egg_id resolution failed', [
                'server_id' => $server->id,
                'pelican_egg_id' => $pelicanEggId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($localEggId === null || (int) $server->egg_id === $localEggId) {
            return;
        }

        try {
            $server->forceFill(['egg_id' => $localEggId])->save();
        } catch (Throwable $e) {
            $logger->warning('modpack: rollback local egg_id update failed', [
                'server_id' => $server->id,
                'target_egg_id' => $localEggId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function setLocalServerStatus(Server $server, string $status, LoggerInterface $logger): void
    {
        if ((string) $server->status === $status) {
            return;
        }
        try {
            $server->forceFill(['status' => $status])->save();
        } catch (Throwable $e) {
            $logger->warning('modpack: rollback local server status update failed', [
                'server_id' => $server->id,
                'target_status' => $status,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Broadcast so the React shell's `useServerLiveUpdates` hook
        // re-runs the sidebar gate without a full reload — same as the
        // install/uninstall flows do via the SyncsServerEggId trait.
        try {
            event(new ServerMirrorChanged(
                serverId: (int) $server->id,
                resource: ServerMirrorChanged::RESOURCE_SERVER,
                action: ServerMirrorChanged::ACTION_UPSERT,
                resourceId: (int) $server->id,
                accessUserIds: $server->accessUsers()->pluck('users.id')->all(),
            ));
        } catch (Throwable $e) {
            $logger->info('modpack: rollback ServerMirrorChanged broadcast failed (non-fatal)', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
