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

    public function authMode(): JsonResponse
    {
        return response()->json([
            'mode' => config('auth-mode.mode'),
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
