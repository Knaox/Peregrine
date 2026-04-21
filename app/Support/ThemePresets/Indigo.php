<?php

namespace App\Support\ThemePresets;

final class Indigo
{
    public static function label(): string { return 'Indigo'; }

    /** @return array<string, string> */
    public static function dark(): array
    {
        return Shells::dark([
            'theme_primary'       => '#6366f1',
            'theme_primary_hover' => '#818cf8',
            'theme_secondary'     => '#f472b6',
            'theme_ring'          => '#818cf8',
            'theme_background'    => '#0c0d1a',
            'theme_surface'       => '#151629',
            'theme_surface_hover' => '#1e2038',
            'theme_surface_elevated' => '#181a2f',
        ]);
    }

    /** @return array<string, string> */
    public static function light(): array
    {
        return Shells::light([
            'theme_primary'       => '#4f46e5',
            'theme_primary_hover' => '#6366f1',
            'theme_secondary'     => '#ec4899',
            'theme_ring'          => '#6366f1',
        ]);
    }
}
