<?php

namespace App\Services;

use App\Services\Theme\CardConfigResolver;
use App\Services\Theme\CssVariableBuilder;
use App\Services\Theme\ThemeAdvancedSettings;
use App\Support\ThemePresets;
use Illuminate\Support\Facades\Cache;

class ThemeService
{
    private ThemeAdvancedSettings $advanced;

    public function __construct(
        private SettingsService $settingsService,
    ) {
        $this->advanced = new ThemeAdvancedSettings($settingsService);
    }

    public function getTheme(): array
    {
        return Cache::remember('theme_full', 3600, fn () => $this->buildTheme());
    }

    private function buildTheme(): array
    {
        return [
            'mode' => $this->settingsService->get('theme_mode', 'dark'),
            'preset' => $this->settingsService->get('theme_preset', 'orange'),
            'colors' => [
                'primary' => $this->settingsService->get('theme_primary', '#f97316'),
                'primary_hover' => $this->settingsService->get('theme_primary_hover', '#fb923c'),
                'secondary' => $this->settingsService->get('theme_secondary', '#8b5cf6'),
                'ring' => $this->settingsService->get('theme_ring', '#fb923c'),
                'danger' => $this->settingsService->get('theme_danger', '#ef4444'),
                'warning' => $this->settingsService->get('theme_warning', '#f59e0b'),
                'success' => $this->settingsService->get('theme_success', '#10b981'),
                'info' => $this->settingsService->get('theme_info', '#3b82f6'),
                // Server lifecycle accents — used by ServerCard / SuspendedOverview
                // / InstallationOverview. Default to warning amber for suspended
                // (matches the existing suspended badge) and info blue for the
                // install-in-progress badge. Admins can pick any colour to match
                // their brand from /admin/theme-settings.
                'suspended' => $this->settingsService->get('theme_suspended', '#f59e0b'),
                'installing' => $this->settingsService->get('theme_installing', '#3b82f6'),
                'background' => $this->settingsService->get('theme_background', '#0c0a14'),
                'surface' => $this->settingsService->get('theme_surface', '#16131e'),
                'surface_hover' => $this->settingsService->get('theme_surface_hover', '#1e1a2a'),
                'surface_elevated' => $this->settingsService->get('theme_surface_elevated', '#1a1724'),
                'border' => $this->settingsService->get('theme_border', '#2a2535'),
                'border_hover' => $this->settingsService->get('theme_border_hover', '#3a3445'),
                'text_primary' => $this->settingsService->get('theme_text_primary', '#f1f0f5'),
                'text_secondary' => $this->settingsService->get('theme_text_secondary', '#8b849e'),
                'text_muted' => $this->settingsService->get('theme_text_muted', '#5a5370'),
            ],
            'radius' => $this->settingsService->get('theme_radius', '0.75rem'),
            'font' => $this->settingsService->get('theme_font', 'Inter'),
            'shadow_intensity' => (int) $this->settingsService->get('theme_shadow_intensity', '50'),
            'density' => $this->settingsService->get('theme_density', 'comfortable'),
            'custom_css' => $this->settingsService->get('theme_custom_css', ''),
            'layout' => $this->advanced->layout(),
            'sidebar_advanced' => $this->advanced->sidebarAdvanced(),
            'login' => $this->advanced->login(),
            'page_overrides' => $this->advanced->pageOverrides(),
            'footer' => $this->advanced->footer(),
            'refinements' => $this->advanced->refinements(),
            'app' => $this->advanced->app(),
        ];
    }

    public function getCssVariables(): array
    {
        return Cache::remember('theme_css_vars', 3600, fn () => $this->buildCssVariables($this->buildTheme()));
    }

    /**
     * CSS variables for a single given mode of the active brand preset.
     * Used by Blade server-rendering to emit the first-paint theme before
     * React mounts — avoids the "brand color flash" problem where the
     * Tailwind/theme.css fallback (orange) shows until /api/settings/theme
     * resolves.
     *
     * @return array<string, string>
     */
    public function getCssVariablesForMode(string $mode): array
    {
        $variants = $this->getModeVariants();

        return $variants[$mode] ?? $variants['dark'];
    }

    /**
     * CSS variables for BOTH modes of the currently-selected brand preset.
     * The client picks which set to apply based on the user's theme_mode
     * (or prefers-color-scheme when mode='auto').
     *
     * @return array{dark: array<string, string>, light: array<string, string>}
     */
    public function getModeVariants(): array
    {
        return Cache::remember('theme_mode_variants', 3600, function (): array {
            $preset = $this->settingsService->get('theme_preset', 'orange');

            return [
                'dark' => $this->buildCssVariables($this->buildThemeFromPreset($preset, 'dark')),
                'light' => $this->buildCssVariables($this->buildThemeFromPreset($preset, 'light')),
            ];
        });
    }

    /**
     * Build a theme array from a preset id + mode, without touching the
     * admin-saved settings. Used to surface both dark/light variants of the
     * current brand preset to the frontend.
     */
    private function buildThemeFromPreset(string $presetId, string $mode): array
    {
        $values = ThemePresets::get($presetId, $mode);

        return [
            'mode' => $values['theme_mode'] ?? $mode,
            'preset' => $presetId,
            'colors' => [
                'primary' => $values['theme_primary'],
                'primary_hover' => $values['theme_primary_hover'],
                'secondary' => $values['theme_secondary'],
                'ring' => $values['theme_ring'],
                'danger' => $values['theme_danger'],
                'warning' => $values['theme_warning'],
                'success' => $values['theme_success'],
                'info' => $values['theme_info'],
                'suspended' => $values['theme_suspended'] ?? '#f59e0b',
                'installing' => $values['theme_installing'] ?? '#3b82f6',
                'background' => $values['theme_background'],
                'surface' => $values['theme_surface'],
                'surface_hover' => $values['theme_surface_hover'],
                'surface_elevated' => $values['theme_surface_elevated'],
                'border' => $values['theme_border'],
                'border_hover' => $values['theme_border_hover'],
                'text_primary' => $values['theme_text_primary'],
                'text_secondary' => $values['theme_text_secondary'],
                'text_muted' => $values['theme_text_muted'],
            ],
            'radius' => $this->settingsService->get('theme_radius', $values['theme_radius']),
            'font' => $this->settingsService->get('theme_font', $values['theme_font']),
            'shadow_intensity' => (int) $this->settingsService->get('theme_shadow_intensity', $values['theme_shadow_intensity']),
            'density' => $this->settingsService->get('theme_density', $values['theme_density']),
            'custom_css' => '',
            'layout' => $this->advanced->layout(),
            'sidebar_advanced' => $this->advanced->sidebarAdvanced(),
            'login' => $this->advanced->login(),
            'page_overrides' => $this->advanced->pageOverrides(),
            'footer' => $this->advanced->footer(),
            'refinements' => $this->advanced->refinements(),
            'app' => $this->advanced->app(),
        ];
    }

    private function buildCssVariables(array $theme): array
    {
        return CssVariableBuilder::build($theme);
    }

    public function getCardConfig(): array
    {
        return CardConfigResolver::mergeJson(
            $this->settingsService->get('card_server_config'),
            CardConfigResolver::cardDefaults(),
        );
    }

    public function getSidebarConfig(): array
    {
        return CardConfigResolver::mergeJson(
            $this->settingsService->get('sidebar_server_config'),
            CardConfigResolver::sidebarDefaults(),
        );
    }

    /**
     * Get the primary color hex for Filament admin panel.
     */
    public function getPrimaryColor(): string
    {
        return $this->settingsService->get('theme_primary', '#f97316');
    }

    /**
     * Clear all theme caches. Call after saving theme settings.
     */
    public function clearCache(): void
    {
        Cache::forget('theme_full');
        Cache::forget('theme_css_vars');
        Cache::forget('theme_mode_variants');
    }
}
