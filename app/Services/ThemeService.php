<?php

namespace App\Services;

use App\Support\ThemePresets;
use Illuminate\Support\Facades\Cache;

class ThemeService
{
    public function __construct(
        private SettingsService $settingsService,
    ) {}

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
        ];
    }

    private function buildCssVariables(array $theme): array
    {
        $vars = [];
        foreach ($theme['colors'] as $key => $value) {
            $cssKey = str_replace('_', '-', $key);
            $vars["--color-{$cssKey}"] = $value;
        }

        // Auto-derive RGB triplets (for rgba() usage in components)
        $vars['--color-primary-rgb'] = $this->hexToRgbTriplet($theme['colors']['primary']);
        $vars['--color-danger-rgb'] = $this->hexToRgbTriplet($theme['colors']['danger']);
        $vars['--color-success-rgb'] = $this->hexToRgbTriplet($theme['colors']['success']);
        $vars['--color-info-rgb'] = $this->hexToRgbTriplet($theme['colors']['info']);
        $vars['--color-warning-rgb'] = $this->hexToRgbTriplet($theme['colors']['warning']);
        $vars['--color-text-secondary-rgb'] = $this->hexToRgbTriplet($theme['colors']['text_secondary']);

        // Auto-derive glow colors (base color with alpha)
        $vars['--color-primary-glow'] = $this->hexToRgba($theme['colors']['primary'], 0.15);
        $vars['--color-danger-glow'] = $this->hexToRgba($theme['colors']['danger'], 0.15);
        $vars['--color-success-glow'] = $this->hexToRgba($theme['colors']['success'], 0.15);

        // Auto-derive glass colors from surface
        $vars['--color-glass'] = $this->hexToRgba($theme['colors']['surface'], 0.75);
        $vars['--color-glass-border'] = $theme['colors']['border'];

        $vars['--radius'] = $theme['radius'];

        // Scale radius variants from base radius
        $radiusVal = (float) $theme['radius'];
        $unit = str_contains($theme['radius'], 'rem') ? 'rem' : 'px';
        $vars['--radius-sm'] = ($radiusVal > 0 ? round($radiusVal * 0.5, 3) : 0) . $unit;
        $vars['--radius-lg'] = ($radiusVal > 0 ? round($radiusVal * 1.33, 3) : 0) . $unit;

        $vars['--font-sans'] = $theme['font'] . ', system-ui, sans-serif';

        // Shadow intensity: 0-100 → 0.0-1.0 scale for shadow alpha multiplier
        $shadowPct = max(0, min(100, (int) $theme['shadow_intensity']));
        $vars['--shadow-intensity'] = (string) round($shadowPct / 100, 3);

        // Density: compact | comfortable | spacious → padding/gap multiplier
        $vars['--density-scale'] = match ($theme['density']) {
            'compact' => '0.75',
            'spacious' => '1.25',
            default => '1',
        };

        // Auto-derive secondary + ring RGB triplets for rgba() usage
        $vars['--color-secondary-rgb'] = $this->hexToRgbTriplet($theme['colors']['secondary']);
        $vars['--color-ring-rgb'] = $this->hexToRgbTriplet($theme['colors']['ring']);

        // Mode-dependent overlay + scrim tokens so components don't need to
        // hardcode rgba(0,0,0,...) or rgba(255,255,255,...) for glass effects.
        $isLight = ($theme['mode'] ?? 'dark') === 'light';
        // Banner overlays sit on top of an egg image (always visually busy),
        // so both modes use a dark gradient to keep the white text legible.
        $vars['--banner-overlay']         = 'rgba(12, 10, 20, 0.92)';
        $vars['--banner-overlay-soft']    = 'rgba(12, 10, 20, 0.55)';
        $vars['--surface-overlay-soft']   = $isLight ? 'rgba(0, 0, 0, 0.04)'       : 'rgba(255, 255, 255, 0.08)';
        $vars['--surface-overlay-strong'] = $isLight ? 'rgba(0, 0, 0, 0.08)'       : 'rgba(255, 255, 255, 0.15)';
        $vars['--surface-overlay-hover']  = $isLight ? 'rgba(0, 0, 0, 0.06)'       : 'rgba(255, 255, 255, 0.06)';
        $vars['--shadow-inset']           = $isLight ? 'inset 0 2px 8px rgba(0, 0, 0, 0.05)' : 'inset 0 2px 8px rgba(0, 0, 0, 0.3)';
        $vars['--modal-scrim']            = $isLight ? 'rgba(15, 23, 42, 0.35)'    : 'rgba(0, 0, 0, 0.7)';
        $vars['--text-on-banner']         = '#ffffff'; // banners always darkened enough to keep white text
        $vars['--scrollbar-thumb']        = $isLight ? 'rgba(0, 0, 0, 0.2)'        : 'rgba(255, 255, 255, 0.15)';
        // Ambient background veil: the AnimatedBackground draws this on top of
        // body so the constellation + orbs get a unified tint. Dark: opaque
        // black-purple to sit on top of the page bg. Light: very soft white
        // brume that lets the body bg and orbs show through without masking.
        // In light mode the overlay would just wash the page — skip it entirely.
        // The orbes + body background provide enough ambiance on their own.
        $vars['--ambient-overlay']        = $isLight ? 'transparent' : 'rgba(12, 10, 20, 0.75)';

        // Egg banner bleed — luminosity blend at 25% opacity works on dark but
        // leaves a distracting texture on a light page. Drop opacity and flip
        // to multiply (darkens light into the egg image instead of revealing it).
        $vars['--egg-bg-opacity'] = $isLight ? '0.15' : '0.25';
        $vars['--egg-bg-blend']   = $isLight ? 'multiply' : 'luminosity';

        return $vars;
    }

    /**
     * Convert hex color to RGB triplet string (e.g. "225, 29, 72").
     */
    private function hexToRgbTriplet(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (str_starts_with($hex, 'rgb')) {
            return $hex;
        }
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return '0, 0, 0';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "{$r}, {$g}, {$b}";
    }

    /**
     * Convert hex color to rgba string.
     */
    private function hexToRgba(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');

        // Skip if already rgba/rgb
        if (str_starts_with($hex, 'rgb')) {
            return $hex;
        }

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return "rgba(0, 0, 0, {$alpha})";
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    }

    public function getCardConfig(): array
    {
        $json = $this->settingsService->get('card_server_config');

        $defaults = [
            'layout' => 'grid',
            'columns' => ['desktop' => 3, 'tablet' => 2, 'mobile' => 1],
            'show_egg_icon' => true,
            'show_egg_name' => true,
            'show_plan_name' => true,
            'show_status_badge' => true,
            'show_stats_bars' => true,
            'show_quick_actions' => true,
            'show_ip_port' => false,
            'show_uptime' => false,
            'card_style' => 'glass',
            'sort_default' => 'name',
            'group_by' => 'none',
        ];

        if ($json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return array_merge($defaults, $decoded);
            }
        }

        return $defaults;
    }

    public function getSidebarConfig(): array
    {
        $json = $this->settingsService->get('sidebar_server_config');

        $defaults = [
            'position' => 'left',
            'style' => 'default',
            'show_server_status' => true,
            'show_server_name' => true,
            'entries' => [
                ['id' => 'overview', 'label_key' => 'servers.detail.overview', 'icon' => 'home', 'enabled' => true, 'route_suffix' => '', 'order' => 0],
                ['id' => 'console', 'label_key' => 'servers.detail.console', 'icon' => 'terminal', 'enabled' => true, 'route_suffix' => '/console', 'order' => 1],
                ['id' => 'files', 'label_key' => 'servers.detail.files', 'icon' => 'folder', 'enabled' => true, 'route_suffix' => '/files', 'order' => 2],
                ['id' => 'databases', 'label_key' => 'servers.detail.databases', 'icon' => 'database', 'enabled' => true, 'route_suffix' => '/databases', 'order' => 3],
                ['id' => 'backups', 'label_key' => 'servers.detail.backups', 'icon' => 'archive', 'enabled' => true, 'route_suffix' => '/backups', 'order' => 4],
                ['id' => 'schedules', 'label_key' => 'servers.detail.schedules', 'icon' => 'clock', 'enabled' => true, 'route_suffix' => '/schedules', 'order' => 5],
                ['id' => 'network', 'label_key' => 'servers.detail.network', 'icon' => 'globe', 'enabled' => true, 'route_suffix' => '/network', 'order' => 6],
                ['id' => 'sftp', 'label_key' => 'servers.detail.sftp', 'icon' => 'key', 'enabled' => true, 'route_suffix' => '/sftp', 'order' => 7],
            ],
        ];

        if ($json) {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                return array_merge($defaults, $decoded);
            }
        }

        return $defaults;
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
