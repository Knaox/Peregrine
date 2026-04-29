/**
 * Raw theme draft state held by the Theme Studio editor. Mirrors the keys
 * that `App\Filament\Pages\Theme\ThemeDefaults::COLORS` exposes server-side
 * — same names so the save endpoint can persist them 1:1.
 *
 * `theme_*` prefixes intentionally preserved (rather than camelCase) so the
 * payload is the same on the wire as what the Filament page sends.
 */
export interface ThemeDraft {
    theme_preset: string;
    theme_mode: 'dark' | 'light' | 'auto';
    theme_primary: string;
    theme_primary_hover: string;
    theme_secondary: string;
    theme_ring: string;
    theme_danger: string;
    theme_warning: string;
    theme_success: string;
    theme_info: string;
    theme_suspended: string;
    theme_installing: string;
    theme_background: string;
    theme_surface: string;
    theme_surface_hover: string;
    theme_surface_elevated: string;
    theme_border: string;
    theme_border_hover: string;
    theme_text_primary: string;
    theme_text_secondary: string;
    theme_text_muted: string;
    theme_radius: string;
    theme_font: string;
    theme_shadow_intensity: number;
    theme_density: 'compact' | 'comfortable' | 'spacious';
    theme_custom_css: string;
    // Layout shell controls (Vague 3 démarrage). Defaults match the prior
    // hardcoded AppLayout (h-16, sticky, max-w-7xl ≈ 1280, comfortable).
    theme_layout_header_height: number;
    theme_layout_header_sticky: boolean;
    theme_layout_header_align: 'default' | 'centered' | 'split';
    theme_layout_container_max: '1280' | '1440' | '1536' | 'full';
    theme_layout_page_padding: 'compact' | 'comfortable' | 'spacious';
    // Sidebar in-server (Vague 3 complète).
    theme_sidebar_classic_width: number;
    theme_sidebar_rail_width: number;
    theme_sidebar_mobile_width: number;
    theme_sidebar_blur_intensity: number;
    theme_sidebar_floating: boolean;
    // Login templates (Vague 3 complète).
    theme_login_template: 'centered' | 'split' | 'overlay' | 'minimal';
    theme_login_background_image: string;
    theme_login_background_blur: number;
    theme_login_background_pattern:
        | 'none'
        | 'gradient'
        | 'mesh'
        | 'dots'
        | 'grid'
        | 'aurora'
        | 'orbs'
        | 'noise';
    // Per-page overrides (Vague 3 complète).
    theme_page_console_fullwidth: boolean;
    theme_page_files_fullwidth: boolean;
    theme_page_dashboard_expanded: boolean;
    // Footer (Vague 3 complète).
    theme_footer_enabled: boolean;
    theme_footer_text: string;
    theme_footer_links: Array<{ label: string; url: string }>;
    // Refinements (Vague 3 complète — "plus de perso").
    theme_animation_speed: 'instant' | 'slower' | 'default' | 'faster';
    theme_hover_scale: 'subtle' | 'default' | 'pronounced';
    theme_border_width: number;
    theme_glass_blur_global: number;
    theme_font_size_scale: 'small' | 'default' | 'large' | 'xl';
    theme_app_background_pattern:
        | 'none'
        | 'gradient'
        | 'mesh'
        | 'dots'
        | 'grid'
        | 'aurora'
        | 'orbs'
        | 'noise';
}

export type PreviewScene =
    | 'dashboard'
    | 'login'
    | 'profile'
    | 'security'
    | 'server_overview'
    | 'server_console'
    | 'server_files'
    | 'server_databases';

export type PreviewBreakpoint = 'mobile' | 'tablet' | 'desktop';

export type PreviewMode = 'dark' | 'light';

/**
 * Scene metadata. `needsServer` scenes can only render once the studio has
 * resolved a sample server identifier (`useThemeStudio.sampleServerId`).
 * The toolbar disables the corresponding button when no server exists.
 */
export interface SceneDefinition {
    /** SPA route pattern. `:id` is substituted at runtime when needsServer. */
    path: string;
    /** True when the route depends on an existing server resource. */
    needsServer: boolean;
}

export const SCENE_DEFINITIONS: Record<PreviewScene, SceneDefinition> = {
    dashboard: { path: '/dashboard', needsServer: false },
    login: { path: '/login', needsServer: false },
    profile: { path: '/profile', needsServer: false },
    security: { path: '/settings/security', needsServer: false },
    server_overview: { path: '/servers/:id', needsServer: true },
    server_console: { path: '/servers/:id/console', needsServer: true },
    server_files: { path: '/servers/:id/files', needsServer: true },
    server_databases: { path: '/servers/:id/databases', needsServer: true },
};

/**
 * Resolves a scene to its concrete iframe URL. Returns null when the scene
 * needs a server but the studio hasn't found one — caller should disable
 * the navigation rather than feed `:id` literally to the SPA router.
 */
export function resolveScenePath(
    scene: PreviewScene,
    serverId: string | null,
): string | null {
    const def = SCENE_DEFINITIONS[scene];
    if (!def.needsServer) return def.path;
    if (!serverId) return null;
    return def.path.replace(':id', serverId);
}

export const BREAKPOINT_WIDTHS: Record<PreviewBreakpoint, number> = {
    mobile: 390,
    tablet: 820,
    desktop: 1440,
};
