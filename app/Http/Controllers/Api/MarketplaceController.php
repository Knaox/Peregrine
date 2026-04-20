<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceService;
use Illuminate\Http\JsonResponse;

class MarketplaceController extends Controller
{
    public function __construct(
        private readonly MarketplaceService $marketplace,
    ) {}

    /**
     * List available plugins from the marketplace registry.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->marketplace->getAvailable(),
        ]);
    }

    /**
     * Install a plugin from the marketplace.
     */
    public function install(string $pluginId): JsonResponse
    {
        try {
            $this->marketplace->install($pluginId);

            return response()->json(['message' => 'Plugin installed.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Update a plugin to the latest version.
     */
    public function update(string $pluginId): JsonResponse
    {
        try {
            $this->marketplace->update($pluginId);

            return response()->json(['message' => 'Plugin updated.']);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Check which plugins have updates available.
     */
    public function checkUpdates(): JsonResponse
    {
        return response()->json([
            'data' => $this->marketplace->checkUpdates(),
        ]);
    }
}
