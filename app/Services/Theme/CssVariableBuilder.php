<?php

namespace App\Services\Theme;

use App\Support\ColorUtils;

/**
 * Translates a normalised theme array (colours + radius + density + font +
 * shadow_intensity + mode) into the flat `--key: value` map consumed by
 * the SPA root style tag.
 *
 * Pure: no settings access, no caching. Inputs in, output out — call from
 * ThemeService::getCssVariables() / getModeVariants() / getCssVariablesForMode().
 */
class CssVariableBuilder
{
    /**
     * @param  array<string, mixed>  $theme
     * @return array<string, string>
     */
    public static function build(array $theme): array
    {
        $vars = [];
        foreach ($theme['colors'] as $key => $value) {
            $cssKey = str_replace('_', '-', $key);
            $vars["--color-{$cssKey}"] = $value;
        }

        // Auto-derive RGB triplets (for rgba() usage in components)
        $vars['--color-primary-rgb'] = ColorUtils::hexToRgbTriplet($theme['colors']['primary']);
        $vars['--color-danger-rgb'] = ColorUtils::hexToRgbTriplet($theme['colors']['danger']);
        $vars['--color-success-rgb'] = ColorUtils::hexToRgbTriplet($theme['colors']['success']);
        $vars['--color-info-rgb'] = ColorUtils::hexToRgbTriplet($theme['colors']['info']);
        $vars['--color-warning-rgb'] = ColorUtils::hexToRgbTriplet($theme['colors']['warning']);
        $vars['--color-text-secondary-rgb'] = ColorUtils::hexToRgbTriplet($theme['colors']['text_secondary']);
        $vars['--color-suspended-rgb'] = ColorUtils::hexToRgbTriplet($theme['colors']['suspended']);
        $vars['--color-installing-rgb'] = ColorUtils::hexToRgbTriplet($theme['colors']['installing']);

        // Auto-derive glow colors (base color with alpha)
        $vars['--color-primary-glow'] = ColorUtils::hexToRgba($theme['colors']['primary'], 0.15);
        $vars['--color-danger-glow'] = ColorUtils::hexToRgba($theme['colors']['danger'], 0.15);
        $vars['--color-success-glow'] = ColorUtils::hexToRgba($theme['colors']['success'], 0.15);
        $vars['--color-suspended-glow'] = ColorUtils::hexToRgba($theme['colors']['suspended'], 0.15);
        $vars['--color-installing-glow'] = ColorUtils::hexToRgba($theme['colors']['installing'], 0.15);

        // Auto-derive glass colors from surface
        $vars['--color-glass'] = ColorUtils::hexToRgba($theme['colors']['surface'], 0.75);
        $vars['--color-glass-border'] = $theme['colors']['border'];

        $vars['--radius'] = $theme['radius'];

        // Scale radius variants from base radius
        $radiusVal = (float) $theme['radius'];
        $unit = str_contains($theme['radius'], 'rem') ? 'rem' : 'px';
        $vars['--radius-sm'] = ($radiusVal > 0 ? round($radiusVal * 0.5, 3) : 0) . $unit;
        $vars['--radius-lg'] = ($radiusVal > 0 ? round($radiusVal * 1.33, 3) : 0) . $unit;

        $vars['--font-sans'] = $theme['font'] . ', system-ui, sans-serif';

        // Shadow intensity: 0-100 → 0.0-1.0 scale for shadow alpha multiplier
        $shadowPct = max(0, min(100, (int) $theme['shadow_intensity']));
        $vars['--shadow-intensity'] = (string) round($shadowPct / 100, 3);

        // Density: compact | comfortable | spacious → padding/gap multiplier
        $vars['--density-scale'] = match ($theme['density']) {
            'compact' => '0.75',
            'spacious' => '1.25',
            default => '1',
        };

        // Auto-derive secondary + ring RGB triplets for rgba() usage
        $vars['--color-secondary-rgb'] = ColorUtils::hexToRgbTriplet($theme['colors']['secondary']);
        $vars['--color-ring-rgb'] = ColorUtils::hexToRgbTriplet($theme['colors']['ring']);

        // Mode-dependent overlay + scrim tokens so components don't need to
        // hardcode rgba(0,0,0,...) or rgba(255,255,255,...) for glass effects.
        $isLight = ($theme['mode'] ?? 'dark') === 'light';
        // Banner overlays sit on top of an egg image (always visually busy),
        // so both modes use a dark gradient to keep the white text legible.
        $vars['--banner-overlay']         = 'rgba(12, 10, 20, 0.92)';
        $vars['--banner-overlay-soft']    = 'rgba(12, 10, 20, 0.55)';
        $vars['--surface-overlay-soft']   = $isLight ? 'rgba(0, 0, 0, 0.04)'       : 'rgba(255, 255, 255, 0.08)';
        $vars['--surface-overlay-strong'] = $isLight ? 'rgba(0, 0, 0, 0.08)'       : 'rgba(255, 255, 255, 0.15)';
        $vars['--surface-overlay-hover']  = $isLight ? 'rgba(0, 0, 0, 0.06)'       : 'rgba(255, 255, 255, 0.06)';
        $vars['--shadow-inset']           = $isLight ? 'inset 0 2px 8px rgba(0, 0, 0, 0.05)' : 'inset 0 2px 8px rgba(0, 0, 0, 0.3)';
        $vars['--modal-scrim']            = $isLight ? 'rgba(15, 23, 42, 0.35)'    : 'rgba(0, 0, 0, 0.7)';
        $vars['--text-on-banner']         = '#ffffff';
        $vars['--scrollbar-thumb']        = $isLight ? 'rgba(0, 0, 0, 0.2)'        : 'rgba(255, 255, 255, 0.15)';
        $vars['--ambient-overlay']        = $isLight ? 'transparent' : 'rgba(12, 10, 20, 0.75)';

        // Egg banner bleed: luminosity blend at 25% opacity works on dark but
        // leaves a distracting texture on a light page. Drop opacity and flip
        // to multiply (darkens light into the egg image instead of revealing it).
        $vars['--egg-bg-opacity'] = $isLight ? '0.15' : '0.25';
        $vars['--egg-bg-blend']   = $isLight ? 'multiply' : 'luminosity';

        return $vars;
    }
}
