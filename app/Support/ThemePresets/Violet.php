<?php

namespace App\Support\ThemePresets;

final class Violet
{
    public static function label(): string { return 'Violet'; }

    /** @return array<string, string> */
    public static function dark(): array
    {
        return Shells::dark([
            'theme_primary'       => '#8b5cf6',
            'theme_primary_hover' => '#a78bfa',
            'theme_secondary'     => '#f472b6',
            'theme_ring'          => '#a78bfa',
            'theme_background'    => '#120c1a',
            'theme_surface'       => '#1c142a',
            'theme_surface_hover' => '#281a3a',
            'theme_surface_elevated' => '#22172f',
        ]);
    }

    /** @return array<string, string> */
    public static function light(): array
    {
        return Shells::light([
            'theme_primary'       => '#7c3aed',
            'theme_primary_hover' => '#8b5cf6',
            'theme_secondary'     => '#ec4899',
            'theme_ring'          => '#8b5cf6',
        ]);
    }
}
