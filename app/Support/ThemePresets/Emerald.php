<?php

namespace App\Support\ThemePresets;

/**
 * Emerald — overrides dark surfaces with cooler green-tinted grays to
 * harmonize with the emerald primary. Light variant swaps the default
 * shell for cool slate surfaces.
 */
final class Emerald
{
    public static function label(): string { return 'Emerald'; }

    /** @return array<string, string> */
    public static function dark(): array
    {
        return Shells::dark([
            'theme_primary'       => '#10b981',
            'theme_primary_hover' => '#34d399',
            'theme_secondary'     => '#f59e0b',
            'theme_ring'          => '#34d399',
            'theme_background'    => '#0a1411',
            'theme_surface'       => '#111f1b',
            'theme_surface_hover' => '#17302a',
            'theme_surface_elevated' => '#132723',
        ]);
    }

    /** @return array<string, string> */
    public static function light(): array
    {
        return Shells::light([
            'theme_primary'       => '#047857',
            'theme_primary_hover' => '#059669',
            'theme_secondary'     => '#d97706',
            'theme_ring'          => '#059669',
            'theme_background'    => '#f8fafc',
            'theme_surface_hover' => '#f1f5f9',
            'theme_border'        => '#e2e8f0',
            'theme_border_hover'  => '#94a3b8',
            'theme_text_primary'  => '#0f172a',
            'theme_text_secondary' => '#334155',
            'theme_text_muted'    => '#475569',
        ]);
    }
}
