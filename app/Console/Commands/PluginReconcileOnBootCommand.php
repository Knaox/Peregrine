<?php

namespace App\Console\Commands;

use App\Models\Plugin;
use App\Services\Plugin\PluginDiscovery;
use App\Services\PluginManager;
use Illuminate\Console\Command;

/**
 * Boot-time reconciliation between the `plugins` table and what's on disk.
 *
 * Two passes :
 *
 *  1. **Zombie row prune** — for each row in `plugins`, if the on-disk
 *     directory has vanished (admin removed it manually, image redeploy
 *     dropped a previously-bundled plugin, …), mark the row inactive.
 *     We never DELETE rows here — that's destructive ; an admin who
 *     re-installs the same plugin id will get their settings + history
 *     back. The relink-public step (next entrypoint line) silently
 *     skips inactive plugins, so a stale symlink can never re-emerge.
 *
 *  2. **Version drift force-resync** — for each row whose on-disk
 *     manifest version differs from the DB version (typical after a
 *     `git pull` on a bundled plugin or a marketplace update that wrote
 *     files but failed mid-DB-write), run the same path as the
 *     `plugin:force-resync` command : sync DB row + run any new
 *     migrations + recreate the public symlink. Idempotent — running
 *     against an already-aligned plugin is a no-op.
 *
 * Designed to be cron-safe and Docker-entrypoint-safe : never throws
 * fatally (each plugin is wrapped in its own try/catch), always
 * returns success so it doesn't block container startup. Errors are
 * logged at WARNING and the container boots normally — broken plugins
 * still get gated by `PluginBootstrap`'s defensive boot at request
 * time.
 *
 * Why a separate command (not just inlined in entrypoint.sh) :
 * artisan commands inherit the full Laravel container, so we have
 * access to `PluginDiscovery`, `PluginManager`, the migrator, etc.
 * without bash gymnastics.
 */
class PluginReconcileOnBootCommand extends Command
{
    protected $signature = 'plugin:reconcile-on-boot {--dry-run : Print what would change without touching the DB or running migrations}';

    protected $description = 'Reconcile the `plugins` table with the on-disk plugin directories : prune zombie rows + force-resync drifted versions';

    public function handle(PluginDiscovery $discovery, PluginManager $manager): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $banner = $dryRun ? '[dry-run] ' : '';

        $rows = Plugin::all();
        if ($rows->isEmpty()) {
            $this->info("{$banner}No plugin rows — nothing to reconcile.");
            return self::SUCCESS;
        }

        $discovered = $discovery->discover();
        $prunedCount = 0;
        $resyncedCount = 0;

        foreach ($rows as $row) {
            try {
                $manifest = $discovered[$row->plugin_id] ?? null;

                // Pass 1 — zombie row : directory gone, plugin row remains.
                if ($manifest === null) {
                    if ($row->is_active) {
                        $this->warn("{$banner}plugin '{$row->plugin_id}' : directory missing — deactivating row");
                        if (! $dryRun) {
                            $row->update(['is_active' => false]);
                        }
                        $prunedCount++;
                    }
                    continue;
                }

                // Pass 2 — version drift : on-disk newer than DB. We don't
                // gate on direction (newer or older) — if they differ at
                // all, force-resync writes the on-disk version + runs any
                // migrations the new version added. The marketplace path
                // already handled the reverse direction (DB > disk after a
                // failed update) ; this catches the other half.
                $diskVersion = (string) ($manifest['version'] ?? '');
                $dbVersion = (string) ($row->version ?? '');

                if ($diskVersion !== '' && $diskVersion !== $dbVersion) {
                    $this->info("{$banner}plugin '{$row->plugin_id}' : disk={$diskVersion} db={$dbVersion} — force-resync");
                    if (! $dryRun) {
                        $manager->forceResync($row->plugin_id);
                    }
                    $resyncedCount++;
                }
            } catch (\Throwable $e) {
                // Single-plugin failure mustn't break the boot. Log and
                // proceed ; the operator sees it on the next visit to
                // /admin/plugins (which now boots even with broken
                // plugins, see PluginBootstrap defensive boot).
                $this->error("plugin '{$row->plugin_id}' : reconcile failed — ".$e->getMessage());
            }
        }

        $this->info("{$banner}reconcile complete : pruned={$prunedCount}, resynced={$resyncedCount}, total_rows={$rows->count()}");

        return self::SUCCESS;
    }
}
