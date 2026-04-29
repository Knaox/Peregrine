import { buildPreviewVariables } from '@/lib/themeStudio/buildPreviewVariables';
import type { PreviewMode, ThemeDraft } from '@/types/themeStudio.types';

interface PresetEntry {
    label: string;
    dark: Record<string, string>;
    light: Record<string, string>;
}

/**
 * The keys a preset can override on a draft. Typography (font, radius,
 * density, shadow) and custom CSS belong to the admin's customisation, not
 * the preset, so they are NOT overwritten when synthesising the
 * inverse-mode draft.
 */
const PRESET_OVERRIDABLE_KEYS = [
    'theme_primary',
    'theme_primary_hover',
    'theme_secondary',
    'theme_ring',
    'theme_danger',
    'theme_warning',
    'theme_success',
    'theme_info',
    'theme_suspended',
    'theme_installing',
    'theme_background',
    'theme_surface',
    'theme_surface_hover',
    'theme_surface_elevated',
    'theme_border',
    'theme_border_hover',
    'theme_text_primary',
    'theme_text_secondary',
    'theme_text_muted',
] as const satisfies ReadonlyArray<keyof ThemeDraft>;

function activeModeOf(draft: ThemeDraft): PreviewMode {
    return draft.theme_mode === 'light' ? 'light' : 'dark';
}

function syntheticDraftFromPreset(
    base: ThemeDraft,
    presetValues: Record<string, string>,
    mode: PreviewMode,
): ThemeDraft {
    const next: ThemeDraft = { ...base, theme_mode: mode };
    for (const key of PRESET_OVERRIDABLE_KEYS) {
        const value = presetValues[key];
        if (typeof value === 'string') {
            (next as unknown as Record<string, unknown>)[key] = value;
        }
    }
    return next;
}

/**
 * Builds the `mode_variants` payload the iframe's ThemeProvider expects so
 * the user can flip the preview mode in the toolbar and see the proper
 * light/dark background+surfaces (not just overlay tweaks on top of dark
 * colours).
 *
 * Mirrors how the live `/api/settings/theme` endpoint composes mode_variants:
 * the active mode uses the admin-saved colours, the inverse mode uses the
 * brand preset's other-mode values. Typography stays consistent across both.
 */
export function buildModeVariants(
    draft: ThemeDraft,
    preset: PresetEntry | null,
): { dark: Record<string, string>; light: Record<string, string> } {
    const active = activeModeOf(draft);
    const inverse: PreviewMode = active === 'dark' ? 'light' : 'dark';

    const activeVars = buildPreviewVariables(draft, active);

    if (!preset) {
        // No preset known — fall back to the active draft for both modes
        // so the iframe still renders something coherent.
        return active === 'dark'
            ? { dark: activeVars, light: buildPreviewVariables(draft, 'light') }
            : { dark: buildPreviewVariables(draft, 'dark'), light: activeVars };
    }

    const inverseValues = inverse === 'dark' ? preset.dark : preset.light;
    const inverseDraft = syntheticDraftFromPreset(draft, inverseValues, inverse);
    const inverseVars = buildPreviewVariables(inverseDraft, inverse);

    return active === 'dark'
        ? { dark: activeVars, light: inverseVars }
        : { dark: inverseVars, light: activeVars };
}
