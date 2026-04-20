<?php

namespace App\Support;

/**
 * Named bundles of theme_* settings the admin can apply in one click.
 *
 * Every preset defines the same 22 keys (18 legacy + 4 new: secondary, ring,
 * shadow_intensity, density) so swapping presets never leaves orphan values.
 */
final class ThemePresets
{
    /**
     * @return array<string, array{label: string, values: array<string, string>}>
     */
    public static function all(): array
    {
        return [
            'orange'  => ['label' => 'Orange (default)', 'values' => self::orange()],
            'amber'   => ['label' => 'Amber',           'values' => self::amber()],
            'crimson' => ['label' => 'Crimson',         'values' => self::crimson()],
            'emerald' => ['label' => 'Emerald',         'values' => self::emerald()],
            'indigo'  => ['label' => 'Indigo',          'values' => self::indigo()],
            'violet'  => ['label' => 'Violet',          'values' => self::violet()],
            'slate'   => ['label' => 'Slate (light)',   'values' => self::slate()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function get(string $id): array
    {
        return self::all()[$id]['values'] ?? self::orange();
    }

    /**
     * Simple {id => label} map for Filament Select options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $map = [];
        foreach (self::all() as $id => $preset) {
            $map[$id] = $preset['label'];
        }
        $map['custom'] = 'Custom';

        return $map;
    }

    // -----------------------------------------------------------------
    // Preset definitions
    // -----------------------------------------------------------------

    /** @return array<string, string> */
    private static function orange(): array
    {
        return self::darkShell([
            'theme_primary'       => '#f97316',
            'theme_primary_hover' => '#fb923c',
            'theme_secondary'     => '#8b5cf6',
            'theme_ring'          => '#fb923c',
        ]);
    }

    /** @return array<string, string> */
    private static function amber(): array
    {
        return self::darkShell([
            'theme_primary'       => '#f59e0b',
            'theme_primary_hover' => '#fbbf24',
            'theme_secondary'     => '#0ea5e9',
            'theme_ring'          => '#fbbf24',
        ]);
    }

    /** @return array<string, string> */
    private static function crimson(): array
    {
        return self::darkShell([
            'theme_primary'       => '#e11d48',
            'theme_primary_hover' => '#f43f5e',
            'theme_secondary'     => '#8b5cf6',
            'theme_ring'          => '#f43f5e',
        ]);
    }

    /** @return array<string, string> */
    private static function emerald(): array
    {
        return self::darkShell([
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
    private static function indigo(): array
    {
        return self::darkShell([
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
    private static function violet(): array
    {
        return self::darkShell([
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
    private static function slate(): array
    {
        return array_merge(self::sharedBase(), [
            'theme_mode'              => 'light',
            'theme_primary'           => '#475569',
            'theme_primary_hover'     => '#64748b',
            'theme_secondary'         => '#0ea5e9',
            'theme_ring'              => '#94a3b8',
            'theme_background'        => '#f8fafc',
            'theme_surface'           => '#ffffff',
            'theme_surface_hover'     => '#f1f5f9',
            'theme_surface_elevated'  => '#ffffff',
            'theme_border'            => '#e2e8f0',
            'theme_border_hover'      => '#cbd5e1',
            'theme_text_primary'      => '#0f172a',
            'theme_text_secondary'    => '#475569',
            'theme_text_muted'        => '#94a3b8',
        ]);
    }

    /**
     * Shared values every preset reuses unless overridden.
     *
     * @return array<string, string>
     */
    private static function sharedBase(): array
    {
        return [
            'theme_danger'           => '#ef4444',
            'theme_warning'          => '#f59e0b',
            'theme_success'          => '#10b981',
            'theme_info'             => '#3b82f6',
            'theme_radius'           => '0.75rem',
            'theme_font'              => 'Inter',
            'theme_custom_css'       => '',
            'theme_shadow_intensity' => '50',       // 0-100 → scales shadow alpha
            'theme_density'          => 'comfortable', // compact | comfortable | spacious
        ];
    }

    /**
     * Dark-mode surfaces reused by all dark presets unless overridden.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private static function darkShell(array $overrides): array
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
}
