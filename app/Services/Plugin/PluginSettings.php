<?php

namespace App\Services\Plugin;

use App\Models\Plugin;

/**
 * Thin KV store for per-plugin settings (stored in the `plugins.settings`
 * JSON column). Split out so the plugin lifecycle service doesn't grow
 * CRUD noise unrelated to activate/deactivate.
 */
class PluginSettings
{
    public function getSetting(string $pluginId, string $key, mixed $default = null): mixed
    {
        $plugin = Plugin::where('plugin_id', $pluginId)->first();

        if (! $plugin) {
            return $default;
        }

        $settings = $plugin->settings ?? [];

        return $settings[$key] ?? $default;
    }

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
}
