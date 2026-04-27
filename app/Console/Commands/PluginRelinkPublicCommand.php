<?php

namespace App\Console\Commands;

use App\Models\Plugin;
use App\Services\Plugin\PluginLifecycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Recreates the `public/plugins/{id}` symlink for every active plugin.
 *
 * Why this exists : in Docker, `public/plugins/` lives in the container's
 * ephemeral filesystem (not in a named volume). The symlink is created by
 * `PluginLifecycle::createPublicSymlink()` only at activation time, so after
 * a redeploy / image pull the symlink is gone but the plugin stays marked
 * active in the DB — the SPA then fetches `/plugins/{id}/bundle.js`, nginx
 * falls back to `/index.php`, and the browser logs `Unexpected token '<'`
 * because it's parsing an HTML response as JS.
 *
 * Called from `docker/entrypoint.sh` after migrations + cache warm. Safe to
 * re-run any time : `relinkPublicAssets()` is idempotent.
 */
class PluginRelinkPublicCommand extends Command
{
    protected $signature = 'plugin:relink-public';

    protected $description = 'Recreate public/plugins/* symlinks for all active plugins (idempotent boot helper).';

    public function handle(PluginLifecycle $lifecycle): int
    {
        // Pre-install / fresh image without migrations yet : the table simply
        // doesn't exist. Nothing to relink, no error to surface.
        if (! Schema::hasTable('plugins')) {
            $this->info('plugins table not yet migrated — nothing to relink.');
            return self::SUCCESS;
        }

        $active = Plugin::where('is_active', true)->pluck('plugin_id');

        if ($active->isEmpty()) {
            $this->info('No active plugins — nothing to relink.');
            return self::SUCCESS;
        }

        foreach ($active as $pluginId) {
            try {
                $lifecycle->relinkPublicAssets($pluginId);
                $this->info("Relinked: {$pluginId}");
            } catch (\Throwable $e) {
                // Don't abort on a single plugin failure — the others must
                // still get their symlinks. The lifecycle already logs the
                // underlying FS error via Log::warning.
                $this->warn("Failed to relink {$pluginId}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
