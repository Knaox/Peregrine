<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Foundation : sanity-check command for the
 * server_plans → server_configurations migration.
 *
 * The actual data copy happens inside the
 * `add_internal_name_and_name_template_to_server_configurations` migration
 * (atomic with the column add). This command is post-migration verification :
 *  - the renamed table exists
 *  - every row has a non-empty internal_name and name_template
 *  - every server with a configuration FK points to an existing row
 *
 * Idempotent and read-only. Safe to invoke at any time.
 */
final class VerifyServerConfigurationsMigration extends Command
{
    protected $signature = 'migrate:verify-server-configurations';

    protected $description = 'Verify the integrity of the server_plans → server_configurations migration.';

    public function handle(): int
    {
        if (! Schema::hasTable('server_configurations')) {
            $this->error('server_configurations table does not exist — migrations not run yet.');
            return self::FAILURE;
        }

        $total = DB::table('server_configurations')->count();
        $this->info("Total server_configurations rows : {$total}");

        $missingInternal = DB::table('server_configurations')
            ->whereNull('internal_name')
            ->orWhere('internal_name', '')
            ->count();

        $missingTemplate = DB::table('server_configurations')
            ->whereNull('name_template')
            ->orWhere('name_template', '')
            ->count();

        if ($missingInternal > 0 || $missingTemplate > 0) {
            $this->error(sprintf(
                'Integrity violation : %d row(s) missing internal_name, %d missing name_template.',
                $missingInternal,
                $missingTemplate,
            ));
            return self::FAILURE;
        }

        $orphanedServers = DB::table('servers')
            ->whereNotNull('server_configuration_id')
            ->whereNotIn(
                'server_configuration_id',
                DB::table('server_configurations')->select('id')
            )
            ->count();

        if ($orphanedServers > 0) {
            $this->error("Integrity violation : {$orphanedServers} server(s) reference a non-existent server_configuration_id.");
            return self::FAILURE;
        }

        $this->info('Verification PASSED.');
        return self::SUCCESS;
    }
}
