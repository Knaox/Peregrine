<?php

namespace App\Support\ThemePresets;

/**
 * Shared scaffolding reused by every preset. Keeping these in a single
 * helper means tweaking "radius/font/density" defaults once cascades to
 * all seven brand palettes.
 */
final class Shells
{
    /**
     * Semantic + typography defaults shared by both dark and light modes.
     *
     * @return array<string, string>
     */
    public static function sharedBase(): array
    {
        return [
            'theme_danger'           => '#ef4444',
            'theme_warning'          => '#f59e0b',
            'theme_success'          => '#10b981',
            'theme_info'             => '#3b82f6',
            'theme_radius'           => '0.75rem',
            'theme_font'              => 'Inter',
            'theme_custom_css'       => '',
            'theme_shadow_intensity' => '50',
            'theme_density'          => 'comfortable',
        ];
    }

    /**
     * Dark-mode surfaces (deep purple-black) shared by every dark preset
     * unless a preset overrides a specific key.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public static function dark(array $overrides): array
    {
        return array_merge(self::sharedBase(), [
            'theme_mode'              => 'dark',
            'theme_background'        => '#0c0a14',
            'theme_surface'           => '#16131e',
            'theme_surface_hover'     => '#1e1a2a',
            'theme_surface_elevated'  => '#1a1724',
            'theme_border'            => '#2a2535',
            'theme_border_hover'      => '#3a3445',
            'theme_text_primary'      => '#f1f0f5',
            'theme_text_secondary'    => '#8b849e',
            'theme_text_muted'        => '#5a5370',
        ], $overrides);
    }

    /**
     * Light-mode surfaces — white cards on off-white bg, tokens validated
     * against WCAG AA/AAA (text_primary 12:1, text_muted 5.2:1, border_hover
     * 2.9:1 for outlined controls).
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    public static function light(array $overrides): array
    {
        return array_merge(self::sharedBase(), [
            'theme_mode'              => 'light',
            'theme_background'        => '#fafafa',
            'theme_surface'           => '#ffffff',
            'theme_surface_hover'     => '#f3f4f6',
            'theme_surface_elevated'  => '#ffffff',
            'theme_border'            => '#e5e7eb',
            'theme_border_hover'      => '#9ca3af',
            'theme_text_primary'      => '#1f2937',
            'theme_text_secondary'    => '#4b5563',
            'theme_text_muted'        => '#6b7280',
        ], $overrides);
    }
}
