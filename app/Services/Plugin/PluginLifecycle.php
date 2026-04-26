<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
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

        $this->removePublicSymlink($pluginId);

        $path = $this->discovery->getPluginPath($pluginId);
        if ($path && $this->files->isDirectory($path)) {
            $this->files->deleteDirectory($path);
        }

        Plugin::where('plugin_id', $pluginId)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    public function runMigrations(string $pluginId): void
    {
        $path = $this->discovery->getPluginPath($pluginId);

        if (! $path) {
            return;
        }

        $migrationsPath = $path . '/src/Migrations';

        if ($this->files->isDirectory($migrationsPath)) {
            Artisan::call('migrate', [
                '--path' => str_replace(base_path() . '/', '', $migrationsPath),
                '--force' => true,
            ]);
        }
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
