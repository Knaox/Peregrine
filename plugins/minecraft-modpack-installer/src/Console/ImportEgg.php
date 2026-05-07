<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Console;

use Illuminate\Console\Command;
use Plugins\MinecraftModpackInstaller\Services\EggImporter;

/**
 * Push the bundled Peregrine Modpack Installer egg into Pelican via the
 * Application API.
 *
 * Three modes :
 *
 *   php artisan modpacks:import-egg              # import only when missing
 *   php artisan modpacks:import-egg --force      # ignore the local cache,
 *                                                  but skip the POST when the
 *                                                  egg already exists in
 *                                                  Pelican (UUID match)
 *   php artisan modpacks:import-egg --hard       # DELETE the Pelican egg
 *                                                  and re-create it from
 *                                                  scratch — recovery path
 *                                                  for a corrupted egg
 *                                                  (variables out of sync
 *                                                  with the bundled template)
 *   php artisan modpacks:import-egg --diagnose   # don't change anything;
 *                                                  print a diff between
 *                                                  Pelican's variables and
 *                                                  the bundled template
 *
 * Lifecycle : install jobs call EggImporter::ensureImported() lazily on
 * the first modpack install, so the bare command is mainly a hand-trigger.
 * `--hard` is the answer when an install job fails with 422 "The X variable
 * field is required" — that's a sign Pelican has stale variable rows.
 */
class ImportEgg extends Command
{
    protected $signature = 'modpacks:import-egg
        {--force : Re-upload, ignoring the local cache}
        {--hard : DELETE the Pelican egg first, then re-import (recovery from drift)}
        {--diagnose : Compare Pelican egg variables against the bundled template; no writes}';

    protected $description = 'Import / refresh / diagnose the Peregrine Modpack Installer egg in Pelican.';

    public function handle(EggImporter $importer): int
    {
        if ($this->option('diagnose')) {
            return $this->runDiagnose($importer);
        }

        if ($this->option('hard')) {
            return $this->runHardReimport($importer);
        }

        try {
            $eggId = $importer->ensureImported(force: (bool) $this->option('force'));
        } catch (\Throwable $e) {
            $this->error('Egg import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pelican egg id = {$eggId}");

        return self::SUCCESS;
    }

    private function runHardReimport(EggImporter $importer): int
    {
        $this->warn('Hard re-import: this will DELETE the existing egg in Pelican.');
        $this->line('Any server currently bound to the modpack-installer egg will need to be swapped back to its native egg afterwards. The modpack flow only mounts this egg transiently, so in practice no long-running server should be affected.');

        if (! $this->confirm('Proceed?', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        try {
            $eggId = $importer->hardReimport();
        } catch (\Throwable $e) {
            $this->error('Hard re-import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pelican egg re-created with id = {$eggId}");

        return self::SUCCESS;
    }

    private function runDiagnose(EggImporter $importer): int
    {
        try {
            $diff = $importer->diagnose();
        } catch (\Throwable $e) {
            $this->error('Diagnose failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('=== Egg variables in Pelican ===');
        $this->line($diff['pelican'] === [] ? '  (egg not found / no variables)' : '  '.implode("\n  ", $diff['pelican']));

        $this->line('');
        $this->line('=== Variables expected by the bundled template ===');
        $this->line('  '.implode("\n  ", $diff['expected']));

        $this->line('');
        if ($diff['missing_in_pelican'] === [] && $diff['extra_in_pelican'] === []) {
            $this->info('Pelican egg matches the bundled template — no drift.');

            return self::SUCCESS;
        }

        $this->warn('Drift detected:');
        if ($diff['missing_in_pelican'] !== []) {
            $this->line('  Missing in Pelican: '.implode(', ', $diff['missing_in_pelican']));
        }
        if ($diff['extra_in_pelican'] !== []) {
            $this->line('  Extra in Pelican (will be removed by --hard): '.implode(', ', $diff['extra_in_pelican']));
        }
        $this->line('');
        $this->line('Recovery: php artisan modpacks:import-egg --hard');

        return self::SUCCESS;
    }
}
