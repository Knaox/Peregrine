<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Purge stale queued jobs whose serialized payload references a plugin namespace.
 *
 * Use case: after an incompatible change to a plugin's Mailable/Job/Event signature,
 * queue rows with the old class shape keep failing on unserialize. This command
 * removes them by scanning the payload text for "Plugins\{StudlyId}\".
 */
class PluginPurgeStaleJobsCommand extends Command
{
    protected $signature = 'plugin:purge-stale-jobs {plugin? : Plugin id (kebab-case). Omit to purge all plugin jobs.}';

    protected $description = 'Delete queued/failed jobs whose payload references a plugin namespace.';

    public function handle(): int
    {
        $pluginArg = $this->argument('plugin');
        $needle = $pluginArg
            ? 'Plugins\\\\' . Str::studly($pluginArg) . '\\\\'
            : 'Plugins\\\\';

        $label = $pluginArg ? "plugin '{$pluginArg}'" : 'all plugins';
        $this->info("Purging stale jobs referencing {$label} (needle: {$needle})...");

        $deletedJobs = 0;
        if (Schema::hasTable('jobs')) {
            $deletedJobs = DB::table('jobs')
                ->where('payload', 'like', '%' . $needle . '%')
                ->delete();
        }

        $deletedFailed = 0;
        if (Schema::hasTable('failed_jobs')) {
            $deletedFailed = DB::table('failed_jobs')
                ->where('payload', 'like', '%' . $needle . '%')
                ->delete();
        }

        $this->line("  jobs table:        {$deletedJobs} row(s) deleted");
        $this->line("  failed_jobs table: {$deletedFailed} row(s) deleted");

        return self::SUCCESS;
    }
}
