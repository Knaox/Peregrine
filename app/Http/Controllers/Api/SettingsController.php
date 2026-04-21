<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandingResource;
use App\Services\SettingsService;
use App\Services\ThemeService;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function __construct(
        private SettingsService $settingsService,
    ) {}

    public function branding(): JsonResponse
    {
        $branding = $this->settingsService->getBranding();

        return response()->json([
            'data' => new BrandingResource($branding),
        ]);
    }

    /**
     * Legacy endpoint kept for the password form — resolves the binary mode
     * from the new multi-flag settings. The richer /api/auth/providers exposes
     * per-provider data for new UI code.
     */
    public function authMode(): JsonResponse
    {
        $localEnabled = $this->settingsService->get('auth_local_enabled', 'true') === 'true';

        return response()->json([
            'mode' => $localEnabled ? 'local' : 'oauth',
        ]);
    }

    public function theme(ThemeService $themeService): JsonResponse
    {
        return response()->json([
            'data' => $themeService->getTheme(),
            'css_variables' => $themeService->getCssVariables(),
            // Both dark + light CSS variables for the active brand preset so the
            // client can swap on user.theme_mode without a round-trip.
            'mode_variants' => $themeService->getModeVariants(),
            'card_config' => $themeService->getCardConfig(),
            'sidebar_config' => $themeService->getSidebarConfig(),
        ]);
    }
}
