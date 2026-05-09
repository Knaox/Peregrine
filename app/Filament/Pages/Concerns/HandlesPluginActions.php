<?php

namespace App\Filament\Pages\Concerns;

use App\Services\MarketplaceService;
use App\Services\PluginManager;
use Filament\Notifications\Notification;

/**
 * Livewire actions invoked from the /admin/plugins page : install, update,
 * activate, deactivate, uninstall, refresh, batch-update. Each action is a
 * thin wrapper that calls the relevant service, fires a notification and
 * reloads the page state. Extracted out of the page controller to keep it
 * under the project's per-file ceiling.
 *
 * Consumers must implement loadPlugins() + loadMarketplace() and expose
 * `array $plugins`.
 */
trait HandlesPluginActions
{
    public function updatePlugin(string $id): void
    {
        try {
            app(MarketplaceService::class)->update($id);

            Notification::make()
                ->title(__('admin/plugins.notifications.plugin_updated_title'))
                ->body(__('admin/plugins.notifications.plugin_updated_body', ['id' => $id]))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('admin/plugins.notifications.update_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->loadPlugins();
        $this->loadMarketplace();
    }

    public function refreshMarketplace(): void
    {
        try {
            app(MarketplaceService::class)->clearCache();
        } catch (\Throwable) {
            // noop — cache store unavailable is non-fatal.
        }

        $this->loadMarketplace();

        Notification::make()
            ->title(__('admin/plugins.notifications.marketplace_refreshed_title'))
            ->body(__('admin/plugins.notifications.marketplace_refreshed_body', ['count' => count($this->marketplacePlugins)]))
            ->success()
            ->send();
    }

    public function activatePlugin(string $id): void
    {
        try {
            app(PluginManager::class)->activate($id);

            Notification::make()
                ->title(__('admin/plugins.notifications.plugin_activated_title'))
                ->body(__('admin/plugins.notifications.plugin_activated_body', ['id' => $id]))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('admin/plugins.notifications.activation_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->loadPlugins();
    }

    public function deactivatePlugin(string $id): void
    {
        app(PluginManager::class)->deactivate($id);

        Notification::make()
            ->title(__('admin/plugins.notifications.plugin_deactivated_title'))
            ->body(__('admin/plugins.notifications.plugin_deactivated_body', ['id' => $id]))
            ->success()
            ->send();

        $this->loadPlugins();
    }

    public function uninstallPlugin(string $id): void
    {
        try {
            app(PluginManager::class)->uninstall($id);

            Notification::make()
                ->title(__('admin/plugins.notifications.plugin_uninstalled_title'))
                ->body(__('admin/plugins.notifications.plugin_uninstalled_body', ['id' => $id]))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('admin/plugins.notifications.uninstall_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->loadPlugins();
        $this->loadMarketplace();
    }

    public function installFromMarketplace(string $id): void
    {
        try {
            app(MarketplaceService::class)->install($id);

            Notification::make()
                ->title(__('admin/plugins.notifications.plugin_installed_title'))
                ->body(__('admin/plugins.notifications.plugin_installed_body', ['id' => $id]))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title(__('admin/plugins.notifications.install_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->loadPlugins();
        $this->loadMarketplace();
    }

    /**
     * Update all installed plugins that have an upgrade pending. Failures on
     * individual plugins do not stop the batch — they are aggregated into a
     * single notification.
     */
    public function updateAllPlugins(): void
    {
        $marketplace = app(MarketplaceService::class);
        $updated = [];
        $failed = [];

        foreach ($this->plugins as $plugin) {
            if (! ($plugin['update_available'] ?? false)) {
                continue;
            }

            try {
                $marketplace->update($plugin['id']);
                $updated[] = $plugin['id'];
            } catch (\Throwable $e) {
                $failed[$plugin['id']] = $e->getMessage();
            }
        }

        if (count($updated) > 0 && count($failed) === 0) {
            Notification::make()
                ->title(__('admin/plugins.notifications.update_all_success_title'))
                ->body(__('admin/plugins.notifications.update_all_success_body', ['count' => count($updated)]))
                ->success()
                ->send();
        } elseif (count($updated) > 0 && count($failed) > 0) {
            Notification::make()
                ->title(__('admin/plugins.notifications.update_all_partial_title'))
                ->body(__('admin/plugins.notifications.update_all_partial_body', [
                    'updated' => count($updated),
                    'failed' => count($failed),
                ]))
                ->warning()
                ->send();
        } elseif (count($failed) > 0) {
            Notification::make()
                ->title(__('admin/plugins.notifications.update_all_failed_title'))
                ->body(implode(', ', array_keys($failed)))
                ->danger()
                ->send();
        }

        $this->loadPlugins();
        $this->loadMarketplace();
    }
}
