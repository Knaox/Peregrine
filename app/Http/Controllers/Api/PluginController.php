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
}
