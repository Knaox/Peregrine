<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Console;

use Illuminate\Console\Command;
use Plugins\MinecraftModpackInstaller\Enums\ModpackInstallationStatus;
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;
use Plugins\MinecraftModpackInstaller\Services\InstallationRollbackService;
use Plugins\MinecraftModpackInstaller\Services\ModpackSettingsService;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Mark stuck modpack installations as failed when their `started_at` is
 * older than the configured timeout. Safety net against the queue worker
 * dying mid-install or the Pelican poll loop being lost across a backend
 * restart — without it, an installation could remain "active" forever and
 * the player would stay locked out of their server.
 *
 * Beyond the DB flip, the cron also asks `InstallationRollbackService` to
 * restore the user's original egg in Pelican for each stuck install.
 * Without that step the row would be marked failed but the server would
 * still be pinned to the modpack-installer egg in Pelican — operator
 * clicks Start, gets the install container, bash never returns. Rollback
 * is per-installation and best-effort: a failure on one row never
 * prevents the cron from processing the rest.
 */
class ReconcileStaleInstallations extends Command
{
    protected $signature = 'modpacks:reconcile-stale-installations';

    protected $description = 'Mark stuck modpack installations as failed and roll their server back to the original egg.';

    public function handle(
        ModpackSettingsService $settings,
        InstallationRollbackService $rollback,
        LoggerInterface $logger,
    ): int {
        $threshold = now()->subMinutes($settings->installTimeoutMinutes());

        // We iterate one row at a time (rather than the previous
        // bulk-update) so each installation's rollback can fire
        // independently; a single Pelican PATCH failure must not stop
        // the cron from clearing the rest of the stale rows. The
        // per-row update inside `failAndRollback` is still cheap because
        // the cron is gated on `started_at < threshold` and the result
        // set is small in practice.
        $stuck = ModpackInstallation::with('server')
            ->whereIn('status', [
                ModpackInstallationStatus::Pending->value,
                ModpackInstallationStatus::Installing->value,
                ModpackInstallationStatus::Uninstalling->value,
                ModpackInstallationStatus::Reinstalling->value,
            ])
            ->where('started_at', '<', $threshold)
            ->get();

        $reconciled = 0;
        foreach ($stuck as $installation) {
            try {
                $rollback->failAndRollback($installation, 'timeout', $logger);
                $reconciled++;
            } catch (Throwable $e) {
                $logger->warning('modpack: reconcile rollback failed', [
                    'installation' => $installation->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Reconciled {$reconciled} stale installation(s).");

        return self::SUCCESS;
    }
}
