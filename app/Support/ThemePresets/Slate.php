<?php

namespace App\Support\ThemePresets;

/**
 * Slate — neutral grayscale preset. Dark variant uses a cool gray primary
 * so nothing "pops". Light variant uses slate-800 primary (#334155) to
 * clear the 3:1 button contrast requirement on the pale bg.
 */
final class Slate
{
    public static function label(): string { return 'Slate'; }

    /** @return array<string, string> */
    public static function dark(): array
    {
        return Shells::dark([
            'theme_primary'       => '#64748b',
            'theme_primary_hover' => '#94a3b8',
            'theme_secondary'     => '#0ea5e9',
            'theme_ring'          => '#94a3b8',
        ]);
    }

    /** @return array<string, string> */
    public static function light(): array
    {
        return Shells::light([
            'theme_primary'       => '#334155',
            'theme_primary_hover' => '#475569',
            'theme_secondary'     => '#0ea5e9',
            'theme_ring'          => '#64748b',
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
