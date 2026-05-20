import type { PreviewMode, ThemeDraft } from '@/types/themeStudio.types';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { SidebarConfig } from '@/hooks/useSidebarConfig';

type ModeVariants = { dark: Record<string, string>; light: Record<string, string> };

/**
 * Assembles the ThemeData-shaped payload posted to the preview iframe so the
 * editor's live preview mirrors what `/api/settings/theme` resolves after
 * save. Pure builder — extracted from useThemeStudio to keep that hook within
 * the 300-line budget (sibling of buildModeVariants).
 *
 * The flat `theme_*` draft keys are folded into the nested `data.*` structure
 * ThemeProvider reads (layout / sidebar_advanced / login / page_overrides /
 * footer / app). Any new `theme_login_*` field must be wired here AND in
 * `ThemeAdvancedSettings::login()` so preview and saved state stay identical.
 */
export function buildPreviewPayload(
    next: ThemeDraft,
    mode: PreviewMode,
    variants: ModeVariants,
    cardConfig: CardConfig | null,
    sidebarConfig: SidebarConfig | null,
) {
    return {
        css_variables: variants[mode],
        mode_variants: variants,
        data: {
            custom_css: next.theme_custom_css,
            font: next.theme_font,
            mode,
            layout: {
                header_height: next.theme_layout_header_height,
                header_sticky: next.theme_layout_header_sticky,
                header_align: next.theme_layout_header_align,
                container_max: next.theme_layout_container_max,
                page_padding: next.theme_layout_page_padding,
            },
            sidebar_advanced: {
                classic_width: next.theme_sidebar_classic_width,
                rail_width: next.theme_sidebar_rail_width,
                mobile_width: next.theme_sidebar_mobile_width,
                blur_intensity: next.theme_sidebar_blur_intensity,
                floating: next.theme_sidebar_floating,
            },
            login: {
                template: next.theme_login_template,
                background_image: next.theme_login_background_image,
                background_blur: next.theme_login_background_blur,
                background_pattern: next.theme_login_background_pattern,
                background_images: next.theme_login_background_images,
                carousel_enabled: next.theme_login_carousel_enabled,
                carousel_interval: next.theme_login_carousel_interval,
                carousel_random: next.theme_login_carousel_random,
                background_opacity: next.theme_login_background_opacity,
                oauth_first: next.theme_login_oauth_first,
            },
            page_overrides: {
                console_fullwidth: next.theme_page_console_fullwidth,
                files_fullwidth: next.theme_page_files_fullwidth,
                dashboard_expanded: next.theme_page_dashboard_expanded,
            },
            footer: {
                enabled: next.theme_footer_enabled,
                text: next.theme_footer_text,
                links: next.theme_footer_links,
            },
            app: {
                background_pattern: next.theme_app_background_pattern,
                shell_variant: next.theme_app_shell_variant,
                rail_width: next.theme_workspace_rail_width,
            },
        },
        card_config: cardConfig,
        sidebar_config: sidebarConfig,
    };
}
