import type { ThemeDraft } from '@/types/themeStudio.types';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { SidebarConfig } from '@/hooks/useSidebarConfig';

/**
 * Client-side mirror of the `theme:export` / `theme:import` CLI commands.
 * Keeps the wire shape (`{ _meta, draft, card_config, sidebar_config }`)
 * identical so a file produced here imports cleanly via
 * `php artisan theme:import` and vice-versa.
 */
export interface ThemeExport {
    _meta: {
        exported_at: string;
        app_url: string;
        schema_version: number;
        name?: string;
    };
    draft: Partial<ThemeDraft>;
    card_config: CardConfig | null;
    sidebar_config: SidebarConfig | null;
}

export interface ParsedImport {
    draft: Partial<ThemeDraft>;
    cardConfig: Partial<CardConfig> | null;
    sidebarConfig: Partial<SidebarConfig> | null;
}

export type ImportResult =
    | { ok: true; value: ParsedImport }
    | { ok: false; error: string };

/**
 * Allow-list of importable draft keys — mirrors the CLI's
 * `array_key_exists(ThemeDefaults::COLORS)` + `theme_footer_links` filter.
 * Unknown keys in an imported file are silently dropped (never persisted).
 */
export const KNOWN_DRAFT_KEYS: ReadonlyArray<keyof ThemeDraft> = [
    'theme_preset', 'theme_mode', 'theme_primary', 'theme_primary_hover',
    'theme_secondary', 'theme_ring', 'theme_danger', 'theme_warning',
    'theme_success', 'theme_info', 'theme_suspended', 'theme_installing',
    'theme_background', 'theme_surface', 'theme_surface_hover',
    'theme_surface_elevated', 'theme_border', 'theme_border_hover',
    'theme_text_primary', 'theme_text_secondary', 'theme_text_muted',
    'theme_radius', 'theme_font', 'theme_shadow_intensity', 'theme_density',
    'theme_custom_css', 'theme_layout_header_height', 'theme_layout_header_sticky',
    'theme_layout_header_align', 'theme_layout_container_max', 'theme_layout_page_padding',
    'theme_sidebar_classic_width', 'theme_sidebar_rail_width', 'theme_sidebar_mobile_width',
    'theme_sidebar_blur_intensity', 'theme_sidebar_floating', 'theme_login_template',
    'theme_login_background_image', 'theme_login_background_blur', 'theme_login_background_pattern',
    'theme_login_background_images', 'theme_login_carousel_enabled', 'theme_login_carousel_interval',
    'theme_login_carousel_random', 'theme_login_background_opacity', 'theme_login_oauth_first',
    'theme_page_console_fullwidth', 'theme_page_files_fullwidth', 'theme_page_dashboard_expanded',
    'theme_footer_enabled', 'theme_footer_text', 'theme_footer_links', 'theme_animation_speed',
    'theme_hover_scale', 'theme_border_width', 'theme_glass_blur_global', 'theme_font_size_scale',
    'theme_app_background_pattern', 'theme_app_shell_variant', 'theme_workspace_rail_width',
];

/**
 * Same blacklist enforced by `ThemeImportCommand` and `SaveThemeRequest`.
 * A malicious export must not be able to smuggle CSS that the HTTP/CLI
 * paths would reject.
 */
export const CUSTOM_CSS_BLACKLIST: ReadonlyArray<RegExp> = [
    /@import\b/i,
    /url\s*\(\s*["']?\s*https?:/i,
    /url\s*\(\s*["']?\s*\/\//i,
    /expression\s*\(/i,
    /behavior\s*:/i,
    /javascript\s*:/i,
    /<\s*script\b/i,
];

/** Builds the export payload from the live studio draft + configs. */
export function buildExportPayload(
    draft: ThemeDraft,
    cardConfig: CardConfig | null,
    sidebarConfig: SidebarConfig | null,
    name?: string,
): ThemeExport {
    return {
        _meta: {
            exported_at: new Date().toISOString(),
            app_url: typeof window !== 'undefined' ? window.location.origin : '',
            schema_version: 1,
            ...(name ? { name } : {}),
        },
        draft,
        card_config: cardConfig,
        sidebar_config: sidebarConfig,
    };
}

export function serializeExport(payload: ThemeExport): string {
    return `${JSON.stringify(payload, null, 2)}\n`;
}

function isRecord(v: unknown): v is Record<string, unknown> {
    return typeof v === 'object' && v !== null && !Array.isArray(v);
}

/**
 * Parses + validates a theme export file. Returns the sanitised draft and
 * configs (unknown keys stripped). Does NOT mutate state — the caller folds
 * the result into the studio draft, which the admin then publishes.
 */
export function parseImport(raw: string): ImportResult {
    let payload: unknown;
    try {
        payload = JSON.parse(raw);
    } catch {
        return { ok: false, error: 'not_json' };
    }
    if (!isRecord(payload) || !isRecord(payload.draft)) {
        return { ok: false, error: 'missing_draft' };
    }

    const rawDraft = payload.draft;
    const customCss = rawDraft.theme_custom_css;
    if (typeof customCss === 'string' && customCss !== '') {
        if (CUSTOM_CSS_BLACKLIST.some((re) => re.test(customCss))) {
            return { ok: false, error: 'unsafe_css' };
        }
    }

    const draft: Partial<ThemeDraft> = {};
    for (const key of KNOWN_DRAFT_KEYS) {
        if (key in rawDraft) {
            // Trust the value shape — the server-side SaveThemeRequest
            // re-validates every field on publish; this is a transport filter.
            (draft as Record<string, unknown>)[key] = rawDraft[key];
        }
    }

    return {
        ok: true,
        value: {
            draft,
            cardConfig: isRecord(payload.card_config) ? (payload.card_config as Partial<CardConfig>) : null,
            sidebarConfig: isRecord(payload.sidebar_config) ? (payload.sidebar_config as Partial<SidebarConfig>) : null,
        },
    };
}
