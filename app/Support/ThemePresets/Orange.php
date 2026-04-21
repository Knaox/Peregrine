<?php

namespace App\Support\ThemePresets;

/**
 * Orange — the default Peregrine brand preset.
 *
 * Light variant uses burnt orange (#c2410c) so primary passes ~3.5:1 on
 * the cream background while keeping the warm brand identity.
 */
final class Orange
{
    public static function label(): string { return 'Orange (default)'; }

    /** @return array<string, string> */
    public static function dark(): array
    {
        return Shells::dark([
            'theme_primary'       => '#f97316',
            'theme_primary_hover' => '#fb923c',
            'theme_secondary'     => '#8b5cf6',
            'theme_ring'          => '#fb923c',
        ]);
    }

    /** @return array<string, string> */
    public static function light(): array
    {
        return Shells::light([
            'theme_primary'       => '#c2410c',
            'theme_primary_hover' => '#ea580c',
            'theme_secondary'     => '#7c3aed',
            'theme_ring'          => '#ea580c',
        ]);
    }
}
