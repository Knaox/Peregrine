<?php

namespace App\Support\ThemePresets;

final class Crimson
{
    public static function label(): string { return 'Crimson'; }

    /** @return array<string, string> */
    public static function dark(): array
    {
        return Shells::dark([
            'theme_primary'       => '#e11d48',
            'theme_primary_hover' => '#f43f5e',
            'theme_secondary'     => '#8b5cf6',
            'theme_ring'          => '#f43f5e',
        ]);
    }

    /** @return array<string, string> */
    public static function light(): array
    {
        return Shells::light([
            'theme_primary'       => '#be123c',
            'theme_primary_hover' => '#e11d48',
            'theme_secondary'     => '#7c3aed',
            'theme_ring'          => '#e11d48',
        ]);
    }
}
