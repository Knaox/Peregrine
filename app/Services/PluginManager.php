<?php

namespace App\Services;

use App\Models\Plugin;
use App\Services\Plugin\PluginBootstrap;
use App\Services\Plugin\PluginDiscovery;
use App\Services\Plugin\PluginLifecycle;
use App\Services\Plugin\PluginSettings;
use Illuminate\Database\Eloquent\Collection;

/**
 * Façade preserving the original PluginManager public API.
 *
 * Internally delegates to 4 sub-services under `App\Services\Plugin\`:
 *   - PluginDiscovery  → filesystem scan + manifest reading (pure)
 *   - PluginLifecycle  → activate / deactivate / uninstall + runtime refresh
 *   - PluginBootstrap  → boot-time autoload + ServiceProvider registration + manifest aggregation
 *   - PluginSettings   → KV store for plugin-specific settings
 *
 * The 15 existing call sites (AppServiceProvider, PluginController,
 * Filament/Plugins, CLI commands, MarketplaceService, SetupController) keep
 * calling `app(PluginManager::class)->method(...)` with no change.
 */
class PluginManager
{
    public function __construct(
        private readonly PluginDiscovery $discovery,
        private readonly PluginLifecycle $lifecycle,
        private readonly PluginBootstrap $bootstrap,
        private readonly PluginSettings $settings,
    ) {}

    // Discovery -----------------------------------------------------------

    /** @return array<string, array<string, mixed>> */
    public function discover(): array
    {
        return $this->discovery->discover();
    }

    /** @return array<string, mixed>|null */
    public function getManifest(string $pluginId): ?array
    {
        return $this->discovery->getManifest($pluginId);
    }

    public function getPluginPath(string $pluginId): ?string
    {
        return $this->discovery->getPluginPath($pluginId);
    }

    // Lifecycle -----------------------------------------------------------

    public function activate(string $pluginId): void
    {
        $this->lifecycle->activate($pluginId);
    }

    public function deactivate(string $pluginId): void
    {
        $this->lifecycle->deactivate($pluginId);
    }

    public function uninstall(string $pluginId): void
    {
        $this->lifecycle->uninstall($pluginId);
    }

    public function runMigrations(string $pluginId): void
    {
        $this->lifecycle->runMigrations($pluginId);
    }

    // Bootstrap -----------------------------------------------------------

    /** @return Collection<int, Plugin> */
    public function getActivePlugins(): Collection
    {
        return $this->bootstrap->getActivePlugins();
    }

    public function bootPlugins(): void
    {
        $this->bootstrap->bootPlugins();
    }

    /** @return array<int, array<string, mixed>> */
    public function getActiveManifests(): array
    {
        return $this->bootstrap->getActiveManifests();
    }

    /** @return array<int, array<string, mixed>> */
    public function allWithStatus(): array
    {
        return $this->bootstrap->allWithStatus();
    }

    // Settings ------------------------------------------------------------

    public function getSetting(string $pluginId, string $key, mixed $default = null): mixed
    {
        return $this->settings->getSetting($pluginId, $key, $default);
    }

    public function setSetting(string $pluginId, string $key, mixed $value): void
    {
        $this->settings->setSetting($pluginId, $key, $value);
    }
}
