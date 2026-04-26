<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plugin;
use App\Services\PluginManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PluginController extends Controller
{
    public function __construct(
        private readonly PluginManager $pluginManager,
    ) {}

    /**
     * List active plugins with frontend manifest (public).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->pluginManager->getActiveManifests(),
        ]);
    }

    /**
     * List all plugins with status (admin only).
     */
    public function all(): JsonResponse
    {
        return response()->json([
            'data' => $this->pluginManager->allWithStatus(),
        ]);
    }

    /**
     * Activate a plugin.
     */
    public function activate(string $pluginId): JsonResponse
    {
        try {
            $this->pluginManager->activate($pluginId);

            return response()->json(['message' => 'Plugin activated.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Deactivate a plugin.
     */
    public function deactivate(string $pluginId): JsonResponse
    {
        $this->pluginManager->deactivate($pluginId);

        return response()->json(['message' => 'Plugin deactivated.']);
    }

    /**
     * Uninstall a plugin (must be inactive).
     */
    public function uninstall(string $pluginId): JsonResponse
    {
        try {
            $this->pluginManager->uninstall($pluginId);

            return response()->json(['message' => 'Plugin uninstalled.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get settings for a plugin.
     */
    public function settings(string $pluginId): JsonResponse
    {
        $plugin = Plugin::where('plugin_id', $pluginId)->first();

        if (! $plugin) {
            return response()->json(['error' => 'Plugin not found.'], 404);
        }

        $manifest = $this->pluginManager->getManifest($pluginId);

        return response()->json([
            'schema' => $manifest['settings_schema'] ?? [],
            'values' => $plugin->settings ?? [],
        ]);
    }

    /**
     * Update settings for a plugin.
     */
    public function updateSettings(Request $request, string $pluginId): JsonResponse
    {
        $plugin = Plugin::where('plugin_id', $pluginId)->first();

        if (! $plugin) {
            return response()->json(['error' => 'Plugin not found.'], 404);
        }

        $plugin->update(['settings' => $request->input('settings', [])]);

        return response()->json(['message' => 'Settings saved.']);
    }

    /**
     * Serve a plugin's i18n bundle for the requested locale (public).
     *
     * Plugins ship their own translations under
     * `plugins/{id}/frontend/i18n/{locale}.json`. The frontend boot loader
     * fetches this and merges it into i18next as the plugin's namespace,
     * so plugins keep their UI strings (and the egg-config-editor's
     * `params.*` translation dictionary) fully self-contained — Peregrine
     * core never carries plugin-specific keys.
     *
     * Falls back to English when the requested locale isn't bundled.
     * Returns `{}` when the plugin has no i18n at all (so the frontend can
     * unconditionally fetch without 404 noise).
     */
    public function i18n(string $pluginId, string $locale): JsonResponse
    {
        // Defensive : sanitize locale to a known shape so it can never
        // escape the i18n directory (no `..`, no slashes).
        if (! preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale)) {
            return response()->json(['error' => 'invalid_locale'], 400);
        }

        $pluginPath = $this->pluginManager->getPluginPath($pluginId);
        if (! $pluginPath) {
            return response()->json(['error' => 'plugin_not_found'], 404);
        }

        $i18nDir = $pluginPath . '/frontend/i18n';
        $candidate = $i18nDir . '/' . $locale . '.json';

        // English fallback : if the plugin doesn't ship the requested
        // locale, return EN so the player still sees translated labels
        // instead of raw keys. Returning {} is the last-resort fallback for
        // plugins that ship no i18n at all.
        if (! is_file($candidate)) {
            $candidate = $i18nDir . '/en.json';
        }
        if (! is_file($candidate)) {
            return response()->json(new \stdClass)->setMaxAge(3600);
        }

        $raw = file_get_contents($candidate);
        if ($raw === false) {
            return response()->json(new \stdClass);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return response()->json(new \stdClass);
        }

        return response()->json($decoded)->setMaxAge(3600);
    }
}
