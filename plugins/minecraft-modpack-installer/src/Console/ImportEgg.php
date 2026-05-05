<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Console;

use Illuminate\Console\Command;
use Plugins\MinecraftModpackInstaller\Services\EggImporter;

/**
 * Push the bundled Peregrine Modpack Installer egg into Pelican via the
 * Application API. Idempotent — Pelican matches on UUID and updates the
 * existing egg row when present. Cached egg id is overwritten with the
 * fresh response, so subsequent install jobs pick up immediately.
 *
 * Usage :
 *   php artisan modpacks:import-egg          # import only if missing
 *   php artisan modpacks:import-egg --force  # always re-upload
 *
 * Lifecycle : the install job calls EggImporter::ensureImported() lazily
 * on the first modpack install, so this command is mainly a hand-trigger
 * for sysadmins who want the egg available in the Pelican picker before
 * any modpack flow has run.
 */
class ImportEgg extends Command
{
    protected $signature = 'modpacks:import-egg {--force : Re-upload even if cached}';

    protected $description = 'Import (or refresh) the Peregrine Modpack Installer egg into Pelican.';

    public function handle(EggImporter $importer): int
    {
        try {
            $eggId = $importer->ensureImported(force: (bool) $this->option('force'));
        } catch (\Throwable $e) {
            $this->error('Egg import failed: '.$e->getMessage());
            return self::FAILURE;
        }

        $this->info("Pelican egg id = {$eggId}");
        return self::SUCCESS;
    }
}
