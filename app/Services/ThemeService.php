<?php

namespace App\Services;

class ThemeService
{
    public function __construct(
        private SettingsService $settingsService,
    ) {}

    public function getTheme(): array
    {
        return [
            'mode' => $this->settingsService->get('theme_mode', 'dark'),
            'colors' => [
                'primary' => $this->settingsService->get('theme_primary', '#e11d48'),
                'primary_hover' => $this->settingsService->get('theme_primary_hover', '#f43f5e'),
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
            'custom_css' => $this->settingsService->get('theme_custom_css', ''),
        ];
    }

    public function getCssVariables(): array
    {
        $theme = $this->getTheme();
        $vars = [];
        foreach ($theme['colors'] as $key => $value) {
            $cssKey = str_replace('_', '-', $key);
            $vars["--color-{$cssKey}"] = $value;
        }

        // Auto-derive RGB triplets (for rgba() usage in components)
        $vars['--color-primary-rgb'] = $this->hexToRgbTriplet($theme['colors']['primary']);
        $vars['--color-danger-rgb'] = $this->hexToRgbTriplet($theme['colors']['danger']);
        $vars['--color-success-rgb'] = $this->hexToRgbTriplet($theme['colors']['success']);

        // Auto-derive glow colors (base color with alpha)
        $vars['--color-primary-glow'] = $this->hexToRgba($theme['colors']['primary'], 0.15);
        $vars['--color-danger-glow'] = $this->hexToRgba($theme['colors']['danger'], 0.15);
        $vars['--color-success-glow'] = $this->hexToRgba($theme['colors']['success'], 0.15);

        // Auto-derive glass colors from surface
        $vars['--color-glass'] = $this->hexToRgba($theme['colors']['surface'], 0.75);
        $vars['--color-glass-border'] = $theme['colors']['border'];

        $vars['--radius'] = $theme['radius'];
        $vars['--font-sans'] = $theme['font'] . ', system-ui, sans-serif';

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
                ['id' => 'databases', 'label_key' => 'servers.detail.databases', 'icon' => 'database', 'enabled' => false, 'route_suffix' => '/databases', 'order' => 3],
                ['id' => 'backups', 'label_key' => 'servers.detail.backups', 'icon' => 'archive', 'enabled' => false, 'route_suffix' => '/backups', 'order' => 4],
                ['id' => 'schedules', 'label_key' => 'servers.detail.schedules', 'icon' => 'clock', 'enabled' => false, 'route_suffix' => '/schedules', 'order' => 5],
                ['id' => 'network', 'label_key' => 'servers.detail.network', 'icon' => 'globe', 'enabled' => false, 'route_suffix' => '/network', 'order' => 6],
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
}
