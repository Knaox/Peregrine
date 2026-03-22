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
                'primary' => $this->settingsService->get('theme_primary', '#f97316'),
                'primary_hover' => $this->settingsService->get('theme_primary_hover', '#ea580c'),
                'danger' => $this->settingsService->get('theme_danger', '#ef4444'),
                'warning' => $this->settingsService->get('theme_warning', '#f59e0b'),
                'success' => $this->settingsService->get('theme_success', '#22c55e'),
                'background' => $this->settingsService->get('theme_background', '#0f172a'),
                'surface' => $this->settingsService->get('theme_surface', '#1e293b'),
                'surface_hover' => $this->settingsService->get('theme_surface_hover', '#334155'),
                'border' => $this->settingsService->get('theme_border', '#334155'),
                'text_primary' => $this->settingsService->get('theme_text_primary', '#f8fafc'),
                'text_secondary' => $this->settingsService->get('theme_text_secondary', '#94a3b8'),
                'text_muted' => $this->settingsService->get('theme_text_muted', '#64748b'),
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
        $vars['--radius'] = $theme['radius'];
        $vars['--font-family'] = $theme['font'] . ', sans-serif';

        return $vars;
    }
}
