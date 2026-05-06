<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Console;

use Illuminate\Console\Command;
use Plugins\MinecraftModpackInstaller\Enums\ModpackInstallationStatus;
use Plugins\MinecraftModpackInstaller\Models\ModpackInstallation;
use Plugins\MinecraftModpackInstaller\Services\ModpackSettingsService;

/**
 * Mark stuck modpack installations as failed when their `started_at` is
 * older than the configured timeout. Safety net against the queue worker
 * dying mid-install or the Pelican poll loop being lost across a backend
 * restart — without it, an installation could remain "active" forever and
 * the player would stay locked out of their server.
 */
class ReconcileStaleInstallations extends Command
{
    protected $signature = 'modpacks:reconcile-stale-installations';

    protected $description = 'Mark stuck modpack installations as failed (timeout safety net).';

    public function handle(ModpackSettingsService $settings): int
    {
        $threshold = now()->subMinutes($settings->installTimeoutMinutes());

        $count = ModpackInstallation::query()
            ->whereIn('status', [
                ModpackInstallationStatus::Pending->value,
                ModpackInstallationStatus::Installing->value,
                ModpackInstallationStatus::Uninstalling->value,
                ModpackInstallationStatus::Reinstalling->value,
            ])
            ->where('started_at', '<', $threshold)
            ->update([
                'status' => ModpackInstallationStatus::Failed->value,
                'status_message' => 'timeout',
                'failed_at' => now(),
            ]);

        $this->info("Reconciled {$count} stale installation(s).");

        return self::SUCCESS;
    }
}
