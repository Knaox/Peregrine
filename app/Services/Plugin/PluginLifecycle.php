<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Activate / deactivate / uninstall + migration + runtime refresh.
 * All the mutating state lives here — the discovery and bootstrap
 * services stay read-only.
 */
class PluginLifecycle
{
    private const CACHE_KEY = 'plugins.discovered';

    public function __construct(
        private readonly PluginDiscovery $discovery,
        private readonly Filesystem $files,
    ) {}

    public function activate(string $pluginId): void
    {
        $manifest = $this->discovery->getManifest($pluginId);

        if (! $manifest) {
            throw new \RuntimeException("Plugin '{$pluginId}' not found on disk.");
        }

        $this->runMigrations($pluginId);
        $this->createPublicSymlink($pluginId);

        Plugin::updateOrCreate(
            ['plugin_id' => $pluginId],
            [
                'is_active' => true,
                'version' => $manifest['version'] ?? '0.0.0',
                'installed_at' => now(),
            ],
        );

        Cache::forget(self::CACHE_KEY);
        $this->refreshRuntime();
    }

    public function deactivate(string $pluginId): void
    {
        Plugin::where('plugin_id', $pluginId)->update(['is_active' => false]);
        $this->removePublicSymlink($pluginId);
        Cache::forget(self::CACHE_KEY);
        $this->purgeStaleJobs($pluginId);
        $this->refreshRuntime();
    }

    public function uninstall(string $pluginId): void
    {
        $plugin = Plugin::where('plugin_id', $pluginId)->first();

        if ($plugin?->is_active) {
            throw new \RuntimeException("Cannot uninstall active plugin '{$pluginId}'. Deactivate it first.");
        }

        // Bundled plugins ship with the panel source — they're tracked in
        // the Peregrine git repo and brought back on every `git pull`.
        // Removing the directory + DB row would just leave an inconsistent
        // state until the next pull restores the files. Refuse upfront.
        $manifest = $this->discovery->getManifest($pluginId);
        if (is_array($manifest) && ! empty($manifest['bundled'])) {
            throw new \RuntimeException(
                "Plugin '{$pluginId}' is bundled with Peregrine and cannot be uninstalled. "
                ."To stop using it, deactivate it (`plugin:deactivate {$pluginId}`) — that's enough "
                ."to unmount its routes and Filament resources without fighting the next git pull."
            );
        }

        // Best-effort symlink cleanup before touching the dir, mirroring
        // deactivate(). Failure here is non-fatal — symlinks may already
        // be gone from a previous attempt.
        $this->removePublicSymlink($pluginId);

        // Atomic delete : verify the directory is actually gone before we
        // wipe the DB row. The previous version just called
        // `deleteDirectory()` without checking the boolean return — so a
        // permission-denied delete left files on disk while the DB record
        // got removed, leaving the panel stuck (re-install fails because
        // dir exists, can't uninstall again because no DB row to find).
        $path = $this->discovery->getPluginPath($pluginId);
        if ($path && $this->files->isDirectory($path)) {
            $deleted = $this->files->deleteDirectory($path);
            if (! $deleted || $this->files->isDirectory($path)) {
                throw new \RuntimeException(
                    "Failed to remove plugin directory at {$path}. "
                    ."The DB record was preserved so the plugin still appears in the admin list — retry once "
                    ."the underlying issue is fixed (typically file ownership : "
                    ."`chown -R \$(id -un):\$(id -gn) {$path}`)."
                );
            }
        }

        Plugin::where('plugin_id', $pluginId)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Sync the DB row to whatever's on disk and run any new migrations.
     * Designed for stuck states :
     *
     *   - Bundled plugin has been git-pulled to a newer version, but the
     *     `plugins` DB row still says the old version. This method bumps
     *     the row + runs the new migrations so the panel reads the new
     *     `version` value and the schema matches the new code.
     *
     *   - A previous install/update partially failed (dir copied OK but
     *     DB row never written). This method writes the row from the
     *     manifest on disk.
     *
     * Does NOT change `is_active` — the admin keeps full control of that
     * via activate/deactivate. Does NOT touch the disk content — for
     * marketplace plugins the operator should re-download via the normal
     * update path; this method is for resyncing metadata only.
     */
    public function forceResync(string $pluginId): void
    {
        $manifest = $this->discovery->getManifest($pluginId);
        if (! $manifest) {
            throw new \RuntimeException(
                "Plugin '{$pluginId}' not found on disk — nothing to resync. "
                ."If this is a marketplace plugin, install it via the marketplace; "
                ."if it's bundled, ensure your panel source is up-to-date (`git pull`)."
            );
        }

        $version = (string) ($manifest['version'] ?? '0.0.0');

        // Run migrations FIRST so the schema is ready before any code that
        // boots from this row hits new columns. Idempotent — runMigrations
        // skips already-recorded migrations.
        $this->runMigrations($pluginId);

        // Recreate the public symlink — it's harmless if it already
        // points at the right target, and fixes the case where a Docker
        // redeploy wiped /public/plugins/.
        $this->createPublicSymlink($pluginId);

        Plugin::updateOrCreate(
            ['plugin_id' => $pluginId],
            [
                // Preserve the existing is_active flag (createOrUpdate fills
                // it with the default `false` on first insert which is the
                // safe choice for a never-activated plugin).
                'version' => $version,
                'installed_at' => Plugin::where('plugin_id', $pluginId)->value('installed_at') ?? now(),
            ],
        );

        Cache::forget(self::CACHE_KEY);
        $this->refreshRuntime();
    }

    /**
     * Run a plugin's migrations one file at a time so a single "table already
     * exists" failure (SQLSTATE[42S01] / MySQL 1050) doesn't abort the whole
     * batch. When that error fires we treat the existing table as authoritative
     * — typically the plugin was reinstalled after an uninstall that didn't
     * drop tables, or an upgrade ships a `create` migration whose target was
     * already created by a previous version under a different filename. We
     * record the migration as completed so subsequent activations are clean.
     *
     * Non-creation errors (column already exists, FK violation, syntax error,
     * etc.) still propagate — only 42S01 is silenced.
     */
    public function runMigrations(string $pluginId): void
    {
        $path = $this->discovery->getPluginPath($pluginId);

        if (! $path) {
            return;
        }

        $migrationsPath = $path . '/src/Migrations';

        if (! $this->files->isDirectory($migrationsPath)) {
            return;
        }

        $files = collect($this->files->files($migrationsPath))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.php'))
            ->sortBy(fn ($file) => $file->getFilename())
            ->values();

        foreach ($files as $file) {
            $migrationName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $relativePath = ltrim(str_replace(base_path(), '', $file->getPathname()), DIRECTORY_SEPARATOR);

            try {
                Artisan::call('migrate', [
                    '--path' => $relativePath,
                    '--force' => true,
                ]);
            } catch (QueryException $e) {
                if (! $this->isTableAlreadyExistsError($e)) {
                    throw $e;
                }

                $this->recordMigrationAsCompleted($migrationName);
                Log::warning("Plugin '{$pluginId}' migration '{$migrationName}' skipped — table already exists. Recorded as completed.");
            }
        }
    }

    private function isTableAlreadyExistsError(QueryException $e): bool
    {
        // SQLSTATE[42S01] = MySQL 1050 = base table or view already exists.
        return ($e->errorInfo[0] ?? null) === '42S01'
            || ($e->errorInfo[1] ?? null) === 1050;
    }

    private function recordMigrationAsCompleted(string $migrationName): void
    {
        if (! Schema::hasTable('migrations')) {
            return;
        }

        if (DB::table('migrations')->where('migration', $migrationName)->exists()) {
            return;
        }

        $batch = ((int) DB::table('migrations')->max('batch')) + 1;

        DB::table('migrations')->insert([
            'migration' => $migrationName,
            'batch' => $batch,
        ]);
    }

    /**
     * Public entry point used by the boot-time relink command
     * (`plugin:relink-public`). Idempotent — recreates the symlink if it
     * disappeared (typical in Docker after a redeploy where the symlink
     * lived in the ephemeral container filesystem rather than a volume).
     */
    public function relinkPublicAssets(string $pluginId): void
    {
        $this->createPublicSymlink($pluginId);
    }

    /**
     * Create a public symlink for plugin frontend assets. Non-fatal — if the
     * web server user can't write to public/ (Docker permission mismatch,
     * read-only filesystem, etc.), the plugin still activates and only the
     * frontend bundle URL is unavailable until the admin fixes the FS.
     */
    private function createPublicSymlink(string $pluginId): void
    {
        $pluginPath = $this->discovery->getPluginPath($pluginId);

        if (! $pluginPath) {
            return;
        }

        $distPath = $pluginPath . '/frontend/dist';
        $publicPath = public_path("plugins/{$pluginId}");

        try {
            $parentDir = dirname($publicPath);
            if (! $this->files->isDirectory($parentDir)) {
                $this->files->makeDirectory($parentDir, 0755, true);
            }

            if ($this->files->exists($publicPath) || is_link($publicPath)) {
                $this->files->delete($publicPath);
            }

            if ($this->files->isDirectory($distPath)) {
                symlink($distPath, $publicPath);
            } else {
                Log::warning("Plugin '{$pluginId}' has no frontend/dist directory at {$distPath} — JS bundle will be served via the controller fallback (slower path). Reinstall or rebuild the plugin's frontend.");
            }
        } catch (\Throwable $e) {
            Log::warning("Plugin symlink skipped for '{$pluginId}': " . $e->getMessage() . '. Ensure public/plugins/ is writable by the web server user (www-data).');
        }
    }

    private function removePublicSymlink(string $pluginId): void
    {
        $publicPath = public_path("plugins/{$pluginId}");

        if (is_link($publicPath)) {
            unlink($publicPath);
        }
    }

    /**
     * Signal queue workers to reload and flush compiled caches after a plugin lifecycle change.
     *
     * Daemon workers keep autoload maps in memory; queue:restart makes them
     * exit gracefully. Route + view + filament-component caches must be
     * cleared so the panel re-discovers the plugin's routes / Filament
     * resources on the next request — Docker prod typically runs
     * `php artisan optimize` at image build, which caches all of these
     * before the plugin was even installed → 404 on the plugin's admin
     * page until clear.
     */
    private function refreshRuntime(): void
    {
        // No-op in the test environment : `config:clear` would nuke any
        // overrides the test set via `config(['key' => 'value'])` and
        // break unrelated tests later in the same process. Production
        // boot flows still get the full cache flush.
        if (app()->environment('testing')) {
            return;
        }

        $commands = [
            'queue:restart',
            'cache:clear',
            'config:clear',
            'route:clear',
            'view:clear',
            'filament:clear-cached-components',
        ];

        foreach ($commands as $cmd) {
            try {
                Artisan::call($cmd);
            } catch (\Throwable) {
                // Non-fatal: lifecycle must keep going even if a cache store is unavailable.
            }
        }
    }

    /**
     * Delete queued/failed jobs whose serialized payload references a given plugin's namespace.
     * Prevents an endless retry loop on stale payloads after an incompatible class change.
     */
    private function purgeStaleJobs(string $pluginId): void
    {
        $needle = '%Plugins\\\\' . Str::studly($pluginId) . '\\\\%';

        try {
            if (Schema::hasTable('jobs')) {
                DB::table('jobs')->where('payload', 'like', $needle)->delete();
            }
            if (Schema::hasTable('failed_jobs')) {
                DB::table('failed_jobs')->where('payload', 'like', $needle)->delete();
            }
        } catch (\Throwable) {
            // Non-fatal: deactivation should succeed even if the jobs table is unreachable.
        }
    }
}
