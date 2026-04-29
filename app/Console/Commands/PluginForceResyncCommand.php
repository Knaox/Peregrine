<?php

namespace App\Console\Commands;

use App\Services\PluginManager;
use Illuminate\Console\Command;

/**
 * Recovery command for stuck plugin states.
 *
 * Use cases :
 *   - A bundled plugin (`invitations`, `egg-config-editor`) was upgraded
 *     via `git pull` so the source on disk is now newer than the DB row.
 *     `plugin:update` doesn't apply — bundled plugins refuse the
 *     download/extract path. This command bumps the DB version + runs
 *     any new migrations.
 *   - A previous install/update partially failed (files moved but DB
 *     row never written). Reads the manifest from disk and writes the
 *     missing DB row.
 *   - The `public/plugins/<id>` symlink got nuked (Docker redeploy on
 *     ephemeral FS) and the bundle 404s in the browser. Recreates it.
 *
 * Does NOT toggle is_active — admin keeps full control of activation
 * via `plugin:activate` / `plugin:deactivate`.
 */
class PluginForceResyncCommand extends Command
{
    protected $signature = 'plugin:force-resync {plugin_id : The plugin ID to resync}';

    protected $description = 'Sync a plugin\'s DB row + migrations to whatever is on disk (recovers stuck states without touching files)';

    public function handle(PluginManager $manager): int
    {
        $pluginId = $this->argument('plugin_id');

        try {
            $this->info("Resyncing plugin: {$pluginId}...");
            $manager->forceResync($pluginId);
            $this->info("Plugin '{$pluginId}' resynced (DB row + migrations + symlink). Activation state unchanged.");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
