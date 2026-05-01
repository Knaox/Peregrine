import type { ThemeDraft, PreviewMode } from '@/types/themeStudio.types';
import { hexToRgbTriplet, hexToRgba } from '@/lib/themeStudio/colorUtils';

/**
 * Client-side mirror of `App\Services\Theme\CssVariableBuilder::build()`.
 *
 * Used by the Theme Studio to render preview changes instantly without a
 * network round-trip. The official server build still runs after every save,
 * so this function only needs to match the PHP output well enough for visual
 * fidelity during editing — exact derivation is recomputed on persist.
 *
 * Keep both implementations in sync. If you add a new CSS variable here,
 * mirror it in CssVariableBuilder.php (and vice versa).
 */
export function buildPreviewVariables(
    draft: ThemeDraft,
    mode: PreviewMode,
): Record<string, string> {
    const vars: Record<string, string> = {
        '--color-primary': draft.theme_primary,
        '--color-primary-hover': draft.theme_primary_hover,
        '--color-secondary': draft.theme_secondary,
        '--color-ring': draft.theme_ring,
        '--color-danger': draft.theme_danger,
        '--color-warning': draft.theme_warning,
        '--color-success': draft.theme_success,
        '--color-info': draft.theme_info,
        '--color-suspended': draft.theme_suspended,
        '--color-installing': draft.theme_installing,
        '--color-background': draft.theme_background,
        '--color-surface': draft.theme_surface,
        '--color-surface-hover': draft.theme_surface_hover,
        '--color-surface-elevated': draft.theme_surface_elevated,
        '--color-border': draft.theme_border,
        '--color-border-hover': draft.theme_border_hover,
        '--color-text-primary': draft.theme_text_primary,
        '--color-text-secondary': draft.theme_text_secondary,
        '--color-text-muted': draft.theme_text_muted,

        '--color-primary-rgb': hexToRgbTriplet(draft.theme_primary),
        '--color-secondary-rgb': hexToRgbTriplet(draft.theme_secondary),
        '--color-ring-rgb': hexToRgbTriplet(draft.theme_ring),
        '--color-danger-rgb': hexToRgbTriplet(draft.theme_danger),
        '--color-success-rgb': hexToRgbTriplet(draft.theme_success),
        '--color-info-rgb': hexToRgbTriplet(draft.theme_info),
        '--color-warning-rgb': hexToRgbTriplet(draft.theme_warning),
        '--color-text-secondary-rgb': hexToRgbTriplet(draft.theme_text_secondary),
        '--color-suspended-rgb': hexToRgbTriplet(draft.theme_suspended),
        '--color-installing-rgb': hexToRgbTriplet(draft.theme_installing),

        '--color-primary-glow': hexToRgba(draft.theme_primary, 0.15),
        '--color-danger-glow': hexToRgba(draft.theme_danger, 0.15),
        '--color-success-glow': hexToRgba(draft.theme_success, 0.15),
        '--color-suspended-glow': hexToRgba(draft.theme_suspended, 0.15),
        '--color-installing-glow': hexToRgba(draft.theme_installing, 0.15),

        '--color-glass': hexToRgba(draft.theme_surface, 0.75),
        '--color-glass-border': draft.theme_border,
    };

    vars['--radius'] = draft.theme_radius;
    const radiusVal = parseFloat(draft.theme_radius) || 0;
    const unit = draft.theme_radius.includes('rem') ? 'rem' : 'px';
    vars['--radius-sm'] = (radiusVal > 0 ? round3(radiusVal * 0.5) : 0) + unit;
    vars['--radius-lg'] = (radiusVal > 0 ? round3(radiusVal * 1.33) : 0) + unit;

    vars['--font-sans'] =
        draft.theme_font === 'system-ui'
            ? 'system-ui, sans-serif'
            : `'${draft.theme_font}', system-ui, sans-serif`;

    const intensity = Math.max(0, Math.min(100, draft.theme_shadow_intensity));
    vars['--shadow-intensity'] = String(round3(intensity / 100));

    vars['--density-scale'] =
        draft.theme_density === 'compact' ? '0.75' : draft.theme_density === 'spacious' ? '1.25' : '1';

    const isLight = mode === 'light';
    vars['--banner-overlay'] = isLight
        ? 'rgba(248, 250, 252, 0.92)'
        : 'rgba(12, 10, 20, 0.92)';
    vars['--banner-overlay-soft'] = isLight
        ? 'rgba(248, 250, 252, 0.55)'
        : 'rgba(12, 10, 20, 0.55)';
    vars['--surface-overlay-soft'] = isLight ? 'rgba(0, 0, 0, 0.04)' : 'rgba(255, 255, 255, 0.08)';
    vars['--surface-overlay-strong'] = isLight ? 'rgba(0, 0, 0, 0.08)' : 'rgba(255, 255, 255, 0.15)';
    vars['--surface-overlay-hover'] = isLight ? 'rgba(0, 0, 0, 0.06)' : 'rgba(255, 255, 255, 0.06)';
    vars['--shadow-inset'] = isLight
        ? 'inset 0 2px 8px rgba(0, 0, 0, 0.05)'
        : 'inset 0 2px 8px rgba(0, 0, 0, 0.3)';
    vars['--modal-scrim'] = isLight ? 'rgba(15, 23, 42, 0.35)' : 'rgba(0, 0, 0, 0.7)';
    vars['--text-on-banner'] = isLight ? draft.theme_text_primary : '#ffffff';
    vars['--scrollbar-thumb'] = isLight ? 'rgba(0, 0, 0, 0.2)' : 'rgba(255, 255, 255, 0.15)';
    vars['--ambient-overlay'] = isLight ? 'transparent' : 'rgba(12, 10, 20, 0.75)';
    vars['--egg-bg-opacity'] = isLight ? '0.15' : '0.25';
    vars['--egg-bg-blend'] = isLight ? 'multiply' : 'luminosity';

    // Layout shell — mirror of CssVariableBuilder::layoutVariables() (PHP).
    Object.assign(vars, layoutVariables(draft));

    // Sidebar in-server — mirror of CssVariableBuilder::sidebarVariables().
    vars['--sidebar-width-classic'] = `${draft.theme_sidebar_classic_width}px`;
    vars['--sidebar-width-rail'] = `${draft.theme_sidebar_rail_width}px`;
    vars['--sidebar-width-mobile'] = `${draft.theme_sidebar_mobile_width}px`;
    vars['--sidebar-blur-intensity'] = `${draft.theme_sidebar_blur_intensity}px`;

    // Refinements — mirror of CssVariableBuilder::refinementVariables().
    const animation =
        draft.theme_animation_speed === 'instant'
            ? '0ms'
            : draft.theme_animation_speed === 'slower'
                ? '450ms'
                : draft.theme_animation_speed === 'faster'
                    ? '150ms'
                    : '250ms';
    const hover =
        draft.theme_hover_scale === 'subtle'
            ? '1.02'
            : draft.theme_hover_scale === 'pronounced'
                ? '1.1'
                : '1.05';
    const fontBase =
        draft.theme_font_size_scale === 'small'
            ? '14px'
            : draft.theme_font_size_scale === 'large'
                ? '18px'
                : draft.theme_font_size_scale === 'xl'
                    ? '20px'
                    : '16px';
    // `--glass-blur` is emitted as a *composite* `backdrop-filter` value —
    // mirrors CssVariableBuilder.php. Consumers do `backdrop-filter:
    // var(--glass-blur)` directly. `--glass-blur-px` exposes just the raw
    // integer for callers that need to compose their own filter.
    const blurPx = draft.theme_glass_blur_global;
    vars['--transition-base'] = animation;
    vars['--hover-scale'] = hover;
    vars['--border-width'] = `${draft.theme_border_width}px`;
    vars['--glass-blur-px'] = `${blurPx}px`;
    vars['--glass-blur'] = `blur(${blurPx}px) saturate(180%)`;
    vars['--font-size-base'] = fontBase;

    return vars;
}

function round3(n: number): number {
    return Math.round(n * 1000) / 1000;
}

const PAGE_PX_PRESETS = {
    compact:  ['0.5rem',  '1rem',    '1.5rem'],
    comfortable: ['0.75rem', '1.5rem',  '2.5rem'],
    spacious: ['1.5rem',  '2.5rem',  '4rem'],
} as const;

const PAGE_PY_PRESETS = {
    compact:  ['0.75rem', '1.5rem', '1.5rem'],
    comfortable: ['1.25rem', '2rem',   '2rem'],
    spacious: ['2rem',    '3rem',   '3rem'],
} as const;

const HEADER_PX_PRESETS = {
    compact:  ['0.75rem', '1rem',   '1.5rem'],
    comfortable: ['1rem',    '1.5rem', '2rem'],
    spacious: ['1.5rem',  '2rem',   '3rem'],
} as const;

const CONTAINER_MAX = {
    '1280': '1280px',
    '1440': '1440px',
    '1536': '1536px',
    'full': '100%',
} as const;

function layoutVariables(draft: ThemeDraft): Record<string, string> {
    const padding = draft.theme_layout_page_padding;
    const px = PAGE_PX_PRESETS[padding];
    const py = PAGE_PY_PRESETS[padding];
    const hpx = HEADER_PX_PRESETS[padding];

    return {
        '--layout-header-height':     `${draft.theme_layout_header_height}px`,
        '--layout-header-align':      draft.theme_layout_header_align,
        '--layout-container-max':     CONTAINER_MAX[draft.theme_layout_container_max],
        '--layout-page-px-mobile':    px[0],
        '--layout-page-px-tablet':    px[1],
        '--layout-page-px-desktop':   px[2],
        '--layout-page-py-mobile':    py[0],
        '--layout-page-py-tablet':    py[1],
        '--layout-page-py-desktop':   py[2],
        '--layout-header-px-mobile':  hpx[0],
        '--layout-header-px-tablet':  hpx[1],
        '--layout-header-px-desktop': hpx[2],
    };
}
