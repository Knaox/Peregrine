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

        // Quote multi-word font families (e.g. "Plus Jakarta Sans") and leave
        // the generic `system-ui` keyword unquoted — quoting `system-ui` would
        // turn it into a family name lookup that will fail on every browser.
        $font = (string) $theme['font'];
        $vars['--font-sans'] = $font === 'system-ui'
            ? 'system-ui, sans-serif'
            : "'{$font}', system-ui, sans-serif";

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
        // Banner overlays sit on top of an egg image (always visually busy).
        // Hardcoded dark in both modes so the egg art stays vivid and the
        // white title/stats text remains readable — Steam/Spotify-style game
        // tile, not a flat coloured strip. A light overlay in light mode
        // washes the egg image out (bright tones blend into the scrim).
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

        // Layout shell controls (Vague 3 démarrage). Defaults reproduce the
        // hardcoded AppLayout exactly so existing installs see no change.
        if (isset($theme['layout']) && is_array($theme['layout'])) {
            foreach (self::layoutVariables($theme['layout']) as $key => $value) {
                $vars[$key] = $value;
            }
        }

        // Sidebar in-server (Vague 3 complète) — widths + glass blur.
        if (isset($theme['sidebar_advanced']) && is_array($theme['sidebar_advanced'])) {
            foreach (self::sidebarVariables($theme['sidebar_advanced']) as $key => $value) {
                $vars[$key] = $value;
            }
        }

        // Refinements (Vague 3 complète) — animation speed, hover scale,
        // border width, global glass blur, font size scale.
        if (isset($theme['refinements']) && is_array($theme['refinements'])) {
            foreach (self::refinementVariables($theme['refinements']) as $key => $value) {
                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    /**
     * @param  array<string, mixed>  $r
     * @return array<string, string>
     */
    private static function refinementVariables(array $r): array
    {
        $animation = match ((string) ($r['animation_speed'] ?? 'default')) {
            'instant' => '0ms',
            'slower'  => '450ms',
            'faster'  => '150ms',
            default   => '250ms',
        };
        $hover = match ((string) ($r['hover_scale'] ?? 'default')) {
            'subtle'     => '1.01',
            'pronounced' => '1.05',
            default      => '1.02',
        };
        $fontBase = match ((string) ($r['font_size_scale'] ?? 'default')) {
            'small' => '14px',
            'large' => '18px',
            'xl'    => '20px',
            default => '16px',
        };

        // `--glass-blur` is emitted as a *composite* `backdrop-filter` value so
        // consumers can plug it directly: `backdrop-filter: var(--glass-blur)`.
        // Older code that wrote `blur(var(--glass-blur)) saturate(180%)` would
        // double-wrap when the var resolved to its `:root` fallback (also a
        // composite), producing invalid CSS — see `--glass-blur-px` for the
        // raw integer if you need to compose your own filter.
        $blurPx = (int) ($r['glass_blur_global'] ?? 16);

        return [
            '--transition-base' => $animation,
            '--hover-scale'     => $hover,
            '--border-width'    => ((int) ($r['border_width'] ?? 1)) . 'px',
            '--glass-blur-px'   => $blurPx . 'px',
            '--glass-blur'      => "blur({$blurPx}px) saturate(180%)",
            '--font-size-base'  => $fontBase,
        ];
    }

    /**
     * Sidebar geometry + blur. Mirrored in TS by buildPreviewVariables.
     *
     * @param  array<string, mixed>  $sidebar
     * @return array<string, string>
     */
    private static function sidebarVariables(array $sidebar): array
    {
        $blur = (int) ($sidebar['blur_intensity'] ?? 12);

        return [
            '--sidebar-width-classic' => ((int) ($sidebar['classic_width'] ?? 224)) . 'px',
            '--sidebar-width-rail'    => ((int) ($sidebar['rail_width'] ?? 64)) . 'px',
            '--sidebar-width-mobile'  => ((int) ($sidebar['mobile_width'] ?? 256)) . 'px',
            '--sidebar-blur-intensity' => $blur . 'px',
        ];
    }

    /**
     * Emit `--layout-*` CSS variables consumed by AppLayout. The frontend
     * mirror lives in `resources/js/lib/themeStudio/buildPreviewVariables.ts`
     * — keep both in sync.
     *
     * Padding presets are defined as 3-tuple `{mobile, tablet, desktop}` per
     * axis. The actual `padding-inline` value is picked at runtime by media
     * queries in `app.css` consuming `--layout-page-px-*`.
     *
     * @param  array<string, mixed>  $layout
     * @return array<string, string>
     */
    private static function layoutVariables(array $layout): array
    {
        $headerHeight = (int) ($layout['header_height'] ?? 64);
        $align = (string) ($layout['header_align'] ?? 'default');
        $padding = (string) ($layout['page_padding'] ?? 'comfortable');
        $containerMax = match ((string) ($layout['container_max'] ?? '1280')) {
            'full' => '100%',
            '1440' => '1440px',
            '1536' => '1536px',
            default => '1280px',
        };

        // page padding presets (rem). mobile / tablet (>=640px) / desktop (>=1024px)
        $pagePx = match ($padding) {
            'compact'  => ['0.5rem',  '1rem',    '1.5rem'],
            'spacious' => ['1.5rem',  '2.5rem',  '4rem'],
            default    => ['0.75rem', '1.5rem',  '2.5rem'], // comfortable
        };
        $pagePy = match ($padding) {
            'compact'  => ['0.75rem', '1.5rem', '1.5rem'],
            'spacious' => ['2rem',    '3rem',   '3rem'],
            default    => ['1.25rem', '2rem',   '2rem'],
        };
        $headerPx = match ($padding) {
            'compact'  => ['0.75rem', '1rem',   '1.5rem'],
            'spacious' => ['1.5rem',  '2rem',   '3rem'],
            default    => ['1rem',    '1.5rem', '2rem'],
        };

        return [
            '--layout-header-height'      => $headerHeight . 'px',
            '--layout-header-align'       => $align,
            '--layout-container-max'      => $containerMax,
            '--layout-page-px-mobile'     => $pagePx[0],
            '--layout-page-px-tablet'     => $pagePx[1],
            '--layout-page-px-desktop'    => $pagePx[2],
            '--layout-page-py-mobile'     => $pagePy[0],
            '--layout-page-py-tablet'     => $pagePy[1],
            '--layout-page-py-desktop'    => $pagePy[2],
            '--layout-header-px-mobile'   => $headerPx[0],
            '--layout-header-px-tablet'   => $headerPx[1],
            '--layout-header-px-desktop'  => $headerPx[2],
        ];
    }
}
