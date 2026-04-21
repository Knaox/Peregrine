<?php

namespace App\Services\Plugin;

use App\Models\Plugin;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Boot-time PSR-4 autoload registration + ServiceProvider boot for active
 * plugins. Called once from AppServiceProvider::boot().
 *
 * Also produces the frontend-facing manifest list (getActiveManifests).
 */
class PluginBootstrap
{
    public function __construct(
        private readonly Application $app,
        private readonly Filesystem $files,
        private readonly PluginDiscovery $discovery,
    ) {}

    /**
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
            $pluginPath = $this->discovery->getPluginPath($plugin->plugin_id);

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
            $manifest = $this->discovery->getManifest($plugin->plugin_id);

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
     * Return manifests of all active plugins (for the frontend API).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getActiveManifests(): array
    {
        $active = $this->getActivePlugins();
        $manifests = [];

        foreach ($active as $plugin) {
            $manifest = $this->discovery->getManifest($plugin->plugin_id);

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
        $discovered = $this->discovery->discover();
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
}
