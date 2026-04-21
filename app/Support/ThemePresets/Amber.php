<?php

namespace App\Support\ThemePresets;

final class Amber
{
    public static function label(): string { return 'Amber'; }

    /** @return array<string, string> */
    public static function dark(): array
    {
        return Shells::dark([
            'theme_primary'       => '#f59e0b',
            'theme_primary_hover' => '#fbbf24',
            'theme_secondary'     => '#0ea5e9',
            'theme_ring'          => '#fbbf24',
        ]);
    }

    /** @return array<string, string> */
    public static function light(): array
    {
        return Shells::light([
            'theme_primary'       => '#b45309',
            'theme_primary_hover' => '#d97706',
            'theme_secondary'     => '#0284c7',
            'theme_ring'          => '#d97706',
        ]);
    }
}
