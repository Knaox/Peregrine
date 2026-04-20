<?php

namespace App\Support;

/**
 * Named bundles of theme_* settings the admin can apply in one click.
 *
 * Every preset ships BOTH a dark and a light variant so users with
 * theme_mode='light' (or 'auto' resolving to light) get a curated,
 * WCAG-readable palette instead of the dark hex values applied to a
 * white background. The admin picks the brand preset globally; the
 * per-user mode decides which variant renders.
 */
final class ThemePresets
{
    /**
     * @return array<string, array{label: string, dark: array<string, string>, light: array<string, string>}>
     */
    public static function all(): array
    {
        return [
            'orange'  => ['label' => 'Orange (default)', 'dark' => self::orangeDark(),  'light' => self::orangeLight()],
            'amber'   => ['label' => 'Amber',            'dark' => self::amberDark(),   'light' => self::amberLight()],
            'crimson' => ['label' => 'Crimson',          'dark' => self::crimsonDark(), 'light' => self::crimsonLight()],
            'emerald' => ['label' => 'Emerald',          'dark' => self::emeraldDark(), 'light' => self::emeraldLight()],
            'indigo'  => ['label' => 'Indigo',           'dark' => self::indigoDark(),  'light' => self::indigoLight()],
            'violet'  => ['label' => 'Violet',           'dark' => self::violetDark(),  'light' => self::violetLight()],
            'slate'   => ['label' => 'Slate',            'dark' => self::slateDark(),   'light' => self::slateLight()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function get(string $id, string $mode = 'dark'): array
    {
        $preset = self::all()[$id] ?? self::all()['orange'];
        $variant = $mode === 'light' ? 'light' : 'dark';

        return $preset[$variant];
    }

    /**
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
    // Dark variants
    // -----------------------------------------------------------------

    /** @return array<string, string> */
    private static function orangeDark(): array
    {
        return self::darkShell([
            'theme_primary'       => '#f97316',
            'theme_primary_hover' => '#fb923c',
            'theme_secondary'     => '#8b5cf6',
            'theme_ring'          => '#fb923c',
        ]);
    }

    /** @return array<string, string> */
    private static function amberDark(): array
    {
        return self::darkShell([
            'theme_primary'       => '#f59e0b',
            'theme_primary_hover' => '#fbbf24',
            'theme_secondary'     => '#0ea5e9',
            'theme_ring'          => '#fbbf24',
        ]);
    }

    /** @return array<string, string> */
    private static function crimsonDark(): array
    {
        return self::darkShell([
            'theme_primary'       => '#e11d48',
            'theme_primary_hover' => '#f43f5e',
            'theme_secondary'     => '#8b5cf6',
            'theme_ring'          => '#f43f5e',
        ]);
    }

    /** @return array<string, string> */
    private static function emeraldDark(): array
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
    private static function indigoDark(): array
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
    private static function violetDark(): array
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
    private static function slateDark(): array
    {
        return self::darkShell([
            'theme_primary'       => '#64748b',
            'theme_primary_hover' => '#94a3b8',
            'theme_secondary'     => '#0ea5e9',
            'theme_ring'          => '#94a3b8',
        ]);
    }

    // -----------------------------------------------------------------
    // Light variants — palettes validated against WCAG AA/AAA
    // Text-primary ≥ 12:1 vs background, primary ≥ 3:1 vs background.
    // -----------------------------------------------------------------

    /** @return array<string, string> */
    private static function orangeLight(): array
    {
        return self::lightShell([
            'theme_primary'       => '#c2410c', // burnt orange keeps brand warmth on cream
            'theme_primary_hover' => '#ea580c',
            'theme_secondary'     => '#7c3aed',
            'theme_ring'          => '#ea580c',
        ]);
    }

    /** @return array<string, string> */
    private static function amberLight(): array
    {
        return self::lightShell([
            'theme_primary'       => '#b45309', // deep amber on neutral gray
            'theme_primary_hover' => '#d97706',
            'theme_secondary'     => '#0284c7',
            'theme_ring'          => '#d97706',
        ]);
    }

    /** @return array<string, string> */
    private static function crimsonLight(): array
    {
        return self::lightShell([
            'theme_primary'       => '#be123c', // deep rose readable on white
            'theme_primary_hover' => '#e11d48',
            'theme_secondary'     => '#7c3aed',
            'theme_ring'          => '#e11d48',
        ]);
    }

    /** @return array<string, string> */
    private static function emeraldLight(): array
    {
        return self::lightShell([
            'theme_primary'       => '#047857', // deep emerald on cool gray
            'theme_primary_hover' => '#059669',
            'theme_secondary'     => '#d97706',
            'theme_ring'          => '#059669',
            'theme_background'    => '#f8fafc',
            'theme_surface_hover' => '#f1f5f9',
            'theme_border'        => '#e2e8f0',
            'theme_border_hover'  => '#94a3b8',  // slate-400, 2.9:1 — visible button outline
            'theme_text_primary'  => '#0f172a',
            'theme_text_secondary' => '#334155', // slate-700, 9:1 AAA
            'theme_text_muted'    => '#475569',  // slate-600, 7:1 AAA (was #94a3b8 2.9 FAIL)
        ]);
    }

    /** @return array<string, string> */
    private static function indigoLight(): array
    {
        return self::lightShell([
            'theme_primary'       => '#4f46e5', // deep indigo on white
            'theme_primary_hover' => '#6366f1',
            'theme_secondary'     => '#ec4899',
            'theme_ring'          => '#6366f1',
        ]);
    }

    /** @return array<string, string> */
    private static function violetLight(): array
    {
        return self::lightShell([
            'theme_primary'       => '#7c3aed', // bright violet on cream
            'theme_primary_hover' => '#8b5cf6',
            'theme_secondary'     => '#ec4899',
            'theme_ring'          => '#8b5cf6',
        ]);
    }

    /** @return array<string, string> */
    private static function slateLight(): array
    {
        return self::lightShell([
            'theme_primary'       => '#334155', // bumped from #475569 to pass 3:1 vs #f8fafc
            'theme_primary_hover' => '#475569',
            'theme_secondary'     => '#0ea5e9',
            'theme_ring'          => '#64748b',
            'theme_background'    => '#f8fafc',
            'theme_surface_hover' => '#f1f5f9',
            'theme_border'        => '#e2e8f0',
            'theme_border_hover'  => '#94a3b8',  // slate-400, 2.9:1
            'theme_text_primary'  => '#0f172a',
            'theme_text_secondary' => '#334155', // slate-700, 9:1 AAA
            'theme_text_muted'    => '#475569',  // slate-600, 7:1 AAA
        ]);
    }

    // -----------------------------------------------------------------
    // Shared shells
    // -----------------------------------------------------------------

    /**
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
            'theme_shadow_intensity' => '50',
            'theme_density'          => 'comfortable',
        ];
    }

    /**
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

    /**
     * Light-mode surfaces. White cards floating on an off-white background
     * preserve the card/elevation distinction without pure-white harshness.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private static function lightShell(array $overrides): array
    {
        return array_merge(self::sharedBase(), [
            'theme_mode'              => 'light',
            'theme_background'        => '#fafafa',
            'theme_surface'           => '#ffffff',
            'theme_surface_hover'     => '#f3f4f6',
            'theme_surface_elevated'  => '#ffffff',
            'theme_border'            => '#e5e7eb',
            // gray-400 instead of gray-300 — gives outlined buttons (Restart)
            // a visible 2.9:1 border on white. Below 3:1 is fine for component
            // boundaries, vs 4.5:1 required for body text.
            'theme_border_hover'      => '#9ca3af',
            'theme_text_primary'      => '#1f2937', // 12:1 AAA
            'theme_text_secondary'    => '#4b5563', // gray-600, 7:1 AAA (was #6b7280 5.2)
            'theme_text_muted'        => '#6b7280', // gray-500, 5.2:1 AA (was #9ca3af 2.9 FAIL)
        ], $overrides);
    }
}
