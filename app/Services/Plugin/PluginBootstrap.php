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
     * Includes the persisted plugin settings alongside the manifest's
     * declared `settings_schema` so plugin bundles can read their own
     * admin-configured options (e.g. egg-config-editor reads
     * `show_raw_key` / `show_description` toggles to add or remove table
     * columns). Plugins should never store secrets in `settings` because
     * this endpoint is reachable by every authenticated user.
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

            // Optional plugin icon — auto-discovered from plugins/{id}/icon.svg.
            // Lets every plugin ship its own logo without manifest changes.
            $iconUrl = null;
            $pluginPath = $this->discovery->getPluginPath($plugin->plugin_id);
            if ($pluginPath !== null && is_file($pluginPath.'/icon.svg')) {
                $iconUrl = "/plugins/{$plugin->plugin_id}/icon.svg";
            }

            // Content-aware ETag of the i18n folder. Hashed from the file
            // mtimes so it flips the moment any locale JSON is edited — even
            // if the plugin's `version` field stays put. Without this,
            // `loadPluginI18n` would only bust the browser's HTTP cache when
            // the operator bumped `plugin.json`, leaving older translations
            // pinned for `max-age=3600` after every i18n hotfix.
            $i18nEtag = null;
            if ($pluginPath !== null) {
                $i18nDir = $pluginPath.'/frontend/i18n';
                if (is_dir($i18nDir)) {
                    $files = glob($i18nDir.'/*.json') ?: [];
                    sort($files);
                    $parts = [];
                    foreach ($files as $f) {
                        $mtime = @filemtime($f);
                        if ($mtime !== false) {
                            $parts[] = basename($f).':'.$mtime;
                        }
                    }
                    if ($parts !== []) {
                        $i18nEtag = substr(sha1(implode('|', $parts)), 0, 10);
                    }
                }
            }

            $assembled = [
                'id' => $manifest['id'],
                'name' => $manifest['name'] ?? $manifest['id'],
                'version' => $manifest['version'] ?? '0.0.0',
                'nav' => $manifest['frontend']['nav'] ?? [],
                'widgets' => $manifest['frontend']['widgets'] ?? [],
                'server_sidebar_entries' => $manifest['frontend']['server_sidebar_entries'] ?? [],
                'server_home_sections' => $manifest['frontend']['server_home_sections'] ?? [],
                'settings_schema' => $manifest['settings_schema'] ?? [],
                'settings' => $plugin->settings ?? [],
                'bundle_url' => $bundleUrl,
                'icon_url' => $iconUrl,
                'i18n_etag' => $i18nEtag,
            ];

            // Plugins can register an enricher closure during boot to inject
            // DB-derived state into their own manifest (e.g. egg-config-editor
            // computes `requires_egg_ids` from rows in egg_config_files so the
            // section is hidden on servers whose egg has no configs declared).
            $assembled = ManifestEnricherRegistry::getInstance()->apply($plugin->plugin_id, $assembled);

            $manifests[] = $assembled;
        }

        return $manifests;
    }

    /**
     * Wire active plugins' Filament resources/pages into the admin panel.
     *
     * Called from `AdminPanelProvider::panel()` so resources are added at
     * panel-construction time — BEFORE Filament builds its routes. The
     * previous approach (registering resources from each plugin's
     * ServiceProvider via `app->booted`) ran AFTER the panel had already
     * built its route list, so plugin admin pages 404'd in production.
     *
     * Each active plugin can ship :
     *   - `src/Filament/Resources/`   → auto-discovered as Resources
     *   - `src/Filament/Pages/`       → auto-discovered as Pages
     *
     * PSR-4 autoload is registered here too because the panel runs during
     * the `register()` phase, BEFORE `bootPlugins()` would normally fire.
     * Registration is idempotent — safe to call from both.
     */
    public function contributeToFilamentPanel(\Filament\Panel $panel): \Filament\Panel
    {
        if (! config('panel.installed')) {
            return $panel;
        }

        try {
            $activePlugins = $this->getActivePlugins();
        } catch (\Throwable) {
            return $panel;
        }

        if ($activePlugins->isEmpty()) {
            return $panel;
        }

        $loader = require base_path('vendor/autoload.php');

        foreach ($activePlugins as $plugin) {
            $pluginPath = $this->discovery->getPluginPath($plugin->plugin_id);
            if (! $pluginPath) {
                continue;
            }

            $studlyId = \Illuminate\Support\Str::studly($plugin->plugin_id);
            $baseNs = "Plugins\\{$studlyId}";
            $srcPath = $pluginPath . '/src/';

            if (! $this->files->isDirectory($srcPath)) {
                continue;
            }

            // Idempotent PSR-4 registration — same call as bootPlugins().
            $loader->addPsr4("{$baseNs}\\", $srcPath);

            $resourcesDir = $srcPath . 'Filament/Resources';
            if ($this->files->isDirectory($resourcesDir)) {
                $panel->discoverResources(
                    in: $resourcesDir,
                    for: "{$baseNs}\\Filament\\Resources",
                );
            }

            $pagesDir = $srcPath . 'Filament/Pages';
            if ($this->files->isDirectory($pagesDir)) {
                $panel->discoverPages(
                    in: $pagesDir,
                    for: "{$baseNs}\\Filament\\Pages",
                );
            }
        }

        return $panel;
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

            // Auto-discovered plugin icon — same convention as getActiveManifests.
            $iconUrl = null;
            $pluginPath = $this->discovery->getPluginPath($id);
            if ($pluginPath !== null && is_file($pluginPath.'/icon.svg')) {
                $iconUrl = "/plugins/{$id}/icon.svg";
            }

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
                // Optional admin URL the plugin exposes for richer config that
                // doesn't fit `settings_schema` (structured data, custom UI).
                // Plugins.blade renders a "Configure" button when set + plugin
                // is active. URL must already be panel-relative (e.g. /admin/foo).
                'manage_url' => $manifest['manage_url'] ?? null,
                'icon_url' => $iconUrl,
            ];
        }

        return $result;
    }
}
