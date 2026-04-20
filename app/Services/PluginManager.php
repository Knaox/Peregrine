<?php

namespace App\Services;

use App\Models\Plugin;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PluginManager
{
    private const CACHE_KEY = 'plugins.discovered';

    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
    ) {}

    /**
     * Discover all plugins by scanning the plugins/ directory.
     *
     * @return array<string, array<string, mixed>>
     */
    public function discover(): array
    {
        $plugins = [];
        $basePath = $this->pluginsBasePath();

        if (! $this->files->isDirectory($basePath)) {
            return $plugins;
        }

        foreach ($this->files->directories($basePath) as $dir) {
            $manifestPath = $dir . '/plugin.json';

            if (! $this->files->exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode($this->files->get($manifestPath), true);

            if (! is_array($manifest) || empty($manifest['id'])) {
                continue;
            }

            $plugins[$manifest['id']] = $manifest;
        }

        return $plugins;
    }

    /**
     * Get all active plugins from the database.
     *
     * @return Collection<int, Plugin>
     */
    public function getActivePlugins(): Collection
    {
        return Plugin::where('is_active', true)->get();
    }

    /**
     * Register autoloaders + boot ServiceProviders for all active plugins.
     * Must register autoloaders first, then boot providers in the same call.
     */
    public function bootPlugins(): void
    {
        if (! config('panel.installed')) {
            return;
        }

        try {
            $activePlugins = $this->getActivePlugins();
        } catch (\Throwable) {
            return;
        }

        $loader = require base_path('vendor/autoload.php');

        // Step 1: Register all autoloaders first
        foreach ($activePlugins as $plugin) {
            $pluginPath = $this->getPluginPath($plugin->plugin_id);

            if (! $pluginPath) {
                continue;
            }

            $studlyId = Str::studly($plugin->plugin_id);
            $srcPath = $pluginPath . '/src/';

            if ($this->files->isDirectory($srcPath)) {
                $loader->addPsr4("Plugins\\{$studlyId}\\", $srcPath);
            }
        }

        // Step 2: Boot ServiceProviders (autoloaders are now all registered)
        foreach ($activePlugins as $plugin) {
            $manifest = $this->getManifest($plugin->plugin_id);

            if (! $manifest) {
                continue;
            }

            $studlyId = Str::studly($plugin->plugin_id);
            $providerClass = $manifest['service_provider'] ?? null;

            if ($providerClass) {
                $fqcn = "Plugins\\{$studlyId}\\{$providerClass}";

                if (class_exists($fqcn)) {
                    $this->app->register($fqcn);
                }
            }
        }
    }

    /**
     * Activate a plugin: run migrations + mark active.
     */
    public function activate(string $pluginId): void
    {
        $manifest = $this->getManifest($pluginId);

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

    /**
     * Deactivate a plugin (tables remain).
     */
    public function deactivate(string $pluginId): void
    {
        Plugin::where('plugin_id', $pluginId)->update(['is_active' => false]);
        $this->removePublicSymlink($pluginId);
        Cache::forget(self::CACHE_KEY);
        $this->purgeStaleJobs($pluginId);
        $this->refreshRuntime();
    }

    /**
     * Uninstall a plugin: remove files + DB record. Only if inactive.
     */
    public function uninstall(string $pluginId): void
    {
        $plugin = Plugin::where('plugin_id', $pluginId)->first();

        if ($plugin?->is_active) {
            throw new \RuntimeException("Cannot uninstall active plugin '{$pluginId}'. Deactivate it first.");
        }

        // Remove public symlink
        $this->removePublicSymlink($pluginId);

        // Remove plugin directory
        $path = $this->getPluginPath($pluginId);

        if ($path && $this->files->isDirectory($path)) {
            $this->files->deleteDirectory($path);
        }

        // Remove DB record
        Plugin::where('plugin_id', $pluginId)->delete();

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Read and parse a plugin's manifest.
     *
     * @return array<string, mixed>|null
     */
    public function getManifest(string $pluginId): ?array
    {
        $path = $this->getPluginPath($pluginId);

        if (! $path) {
            return null;
        }

        $manifestPath = $path . '/plugin.json';

        if (! $this->files->exists($manifestPath)) {
            return null;
        }

        $data = json_decode($this->files->get($manifestPath), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Resolve the filesystem path of a plugin.
     */
    public function getPluginPath(string $pluginId): ?string
    {
        $path = $this->pluginsBasePath() . '/' . $pluginId;

        return $this->files->isDirectory($path) ? $path : null;
    }

    /**
     * Read a single setting for a plugin.
     */
    public function getSetting(string $pluginId, string $key, mixed $default = null): mixed
    {
        $plugin = Plugin::where('plugin_id', $pluginId)->first();

        if (! $plugin) {
            return $default;
        }

        $settings = $plugin->settings ?? [];

        return $settings[$key] ?? $default;
    }

    /**
     * Write a single setting for a plugin.
     */
    public function setSetting(string $pluginId, string $key, mixed $value): void
    {
        $plugin = Plugin::where('plugin_id', $pluginId)->first();

        if (! $plugin) {
            return;
        }

        $settings = $plugin->settings ?? [];
        $settings[$key] = $value;
        $plugin->update(['settings' => $settings]);
    }

    /**
     * Run migrations from a plugin's src/Migrations/ directory.
     */
    public function runMigrations(string $pluginId): void
    {
        $path = $this->getPluginPath($pluginId);

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
     * Return manifests of all active plugins (for the frontend API).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveManifests(): array
    {
        $active = $this->getActivePlugins();
        $manifests = [];

        foreach ($active as $plugin) {
            $manifest = $this->getManifest($plugin->plugin_id);

            if (! $manifest) {
                continue;
            }

            $bundleUrl = null;
            $frontendBundle = $manifest['frontend']['bundle'] ?? null;

            if ($frontendBundle) {
                $version = $manifest['version'] ?? '0';
                $bundleUrl = "/plugins/{$plugin->plugin_id}/bundle.js?v={$version}";
            }

            $manifests[] = [
                'id' => $manifest['id'],
                'name' => $manifest['name'] ?? $manifest['id'],
                'version' => $manifest['version'] ?? '0.0.0',
                'nav' => $manifest['frontend']['nav'] ?? [],
                'widgets' => $manifest['frontend']['widgets'] ?? [],
                'server_sidebar_entries' => $manifest['frontend']['server_sidebar_entries'] ?? [],
                'bundle_url' => $bundleUrl,
            ];
        }

        return $manifests;
    }

    /**
     * Return all plugins: discovered on disk merged with DB state.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allWithStatus(): array
    {
        $discovered = $this->discover();
        $dbPlugins = Plugin::all()->keyBy('plugin_id');
        $result = [];

        foreach ($discovered as $id => $manifest) {
            $dbRecord = $dbPlugins->get($id);

            $result[] = [
                'id' => $id,
                'name' => $manifest['name'] ?? $id,
                'description' => $manifest['description'] ?? '',
                'version' => $manifest['version'] ?? '0.0.0',
                'author' => $manifest['author'] ?? '',
                'license' => $manifest['license'] ?? '',
                'is_active' => $dbRecord?->is_active ?? false,
                'is_installed' => $dbRecord !== null,
                'installed_at' => $dbRecord?->installed_at,
                'settings_schema' => $manifest['settings_schema'] ?? [],
                'settings' => $dbRecord?->settings ?? [],
            ];
        }

        return $result;
    }

    /**
     * Create a public symlink for plugin frontend assets.
     */
    private function createPublicSymlink(string $pluginId): void
    {
        $pluginPath = $this->getPluginPath($pluginId);

        if (! $pluginPath) {
            return;
        }

        $distPath = $pluginPath . '/frontend/dist';
        $publicPath = public_path("plugins/{$pluginId}");

        // Ensure parent directory exists
        $parentDir = dirname($publicPath);

        if (! $this->files->isDirectory($parentDir)) {
            $this->files->makeDirectory($parentDir, 0755, true);
        }

        // Remove existing symlink or directory
        if ($this->files->exists($publicPath) || is_link($publicPath)) {
            $this->files->delete($publicPath);
        }

        if ($this->files->isDirectory($distPath)) {
            symlink($distPath, $publicPath);
        }
    }

    /**
     * Remove the public symlink for a plugin.
     */
    private function removePublicSymlink(string $pluginId): void
    {
        $publicPath = public_path("plugins/{$pluginId}");

        if (is_link($publicPath)) {
            unlink($publicPath);
        }
    }

    private function pluginsBasePath(): string
    {
        return base_path('plugins');
    }

    /**
     * Signal queue workers to reload and flush compiled caches after a plugin lifecycle change.
     * Daemon workers keep autoload maps in memory; queue:restart makes them exit gracefully.
     */
    private function refreshRuntime(): void
    {
        foreach (['queue:restart', 'cache:clear', 'config:clear'] as $cmd) {
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
