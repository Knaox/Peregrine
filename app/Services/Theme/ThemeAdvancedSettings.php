<?php

namespace App\Services\Theme;

use App\Services\SettingsService;

/**
 * Pure builders for the sub-sections of `ThemeService::getTheme()` that
 * grew with Vague 3 (layout, sidebar advanced, login, page overrides,
 * footer, refinements). Extracted from ThemeService to keep that file
 * within the 300-line project budget.
 *
 * Each method returns a flat associative array consumed by:
 *   - the JSON payload at /api/settings/theme (read by ThemeProvider)
 *   - CssVariableBuilder when the input is a CSS variable contributor
 */
final class ThemeAdvancedSettings
{
    public function __construct(private SettingsService $settings) {}

    /** @return array<string, mixed> */
    public function layout(): array
    {
        $sticky = (string) $this->settings->get('theme_layout_header_sticky', '1');

        return [
            'header_height' => (int) $this->settings->get('theme_layout_header_height', '64'),
            'header_sticky' => $sticky === '1' || $sticky === 'true',
            'header_align' => (string) $this->settings->get('theme_layout_header_align', 'default'),
            'container_max' => (string) $this->settings->get('theme_layout_container_max', '1280'),
            'page_padding' => (string) $this->settings->get('theme_layout_page_padding', 'comfortable'),
        ];
    }

    /** @return array<string, mixed> */
    public function sidebarAdvanced(): array
    {
        $floating = (string) $this->settings->get('theme_sidebar_floating', '0');

        return [
            'classic_width' => (int) $this->settings->get('theme_sidebar_classic_width', '224'),
            'rail_width' => (int) $this->settings->get('theme_sidebar_rail_width', '64'),
            'mobile_width' => (int) $this->settings->get('theme_sidebar_mobile_width', '256'),
            'blur_intensity' => (int) $this->settings->get('theme_sidebar_blur_intensity', '12'),
            'floating' => $floating === '1' || $floating === 'true',
        ];
    }

    /** @return array<string, mixed> */
    public function login(): array
    {
        $imagesRaw = (string) $this->settings->get('theme_login_background_images', '[]');
        $images = json_decode($imagesRaw, true);
        if (! is_array($images)) {
            $images = [];
        }
        $carouselEnabled = (string) $this->settings->get('theme_login_carousel_enabled', '0');
        $carouselRandom = (string) $this->settings->get('theme_login_carousel_random', '1');

        return [
            'template' => (string) $this->settings->get('theme_login_template', 'centered'),
            'background_image' => (string) $this->settings->get('theme_login_background_image', ''),
            'background_blur' => (int) $this->settings->get('theme_login_background_blur', '0'),
            'background_pattern' => (string) $this->settings->get('theme_login_background_pattern', 'gradient'),
            'background_images' => array_values(array_filter($images, 'is_string')),
            'carousel_enabled' => $carouselEnabled === '1' || $carouselEnabled === 'true',
            'carousel_interval' => (int) $this->settings->get('theme_login_carousel_interval', '6000'),
            'carousel_random' => $carouselRandom === '1' || $carouselRandom === 'true',
            'background_opacity' => (int) $this->settings->get('theme_login_background_opacity', '100'),
        ];
    }

    /** @return array<string, bool> */
    public function pageOverrides(): array
    {
        $bool = fn (string $key): bool => in_array(
            (string) $this->settings->get($key, '0'),
            ['1', 'true'], true,
        );

        return [
            'console_fullwidth' => $bool('theme_page_console_fullwidth'),
            'files_fullwidth' => $bool('theme_page_files_fullwidth'),
            'dashboard_expanded' => $bool('theme_page_dashboard_expanded'),
        ];
    }

    /** @return array<string, mixed> */
    public function footer(): array
    {
        $enabled = (string) $this->settings->get('theme_footer_enabled', '0');
        $linksRaw = (string) $this->settings->get('theme_footer_links', '[]');
        $links = json_decode($linksRaw, true);
        if (! is_array($links)) {
            $links = [];
        }

        return [
            'enabled' => $enabled === '1' || $enabled === 'true',
            'text' => (string) $this->settings->get('theme_footer_text', ''),
            'links' => array_values(array_filter(
                $links,
                fn ($l): bool => is_array($l) && isset($l['label'], $l['url']),
            )),
        ];
    }

    /** @return array<string, string> */
    public function refinements(): array
    {
        return [
            'animation_speed' => (string) $this->settings->get('theme_animation_speed', 'default'),
            'hover_scale' => (string) $this->settings->get('theme_hover_scale', 'default'),
            'border_width' => (string) $this->settings->get('theme_border_width', '1'),
            'glass_blur_global' => (string) $this->settings->get('theme_glass_blur_global', '16'),
            'font_size_scale' => (string) $this->settings->get('theme_font_size_scale', 'default'),
        ];
    }

    /** @return array<string, mixed> */
    public function app(): array
    {
        return [
            'background_pattern' => (string) $this->settings->get('theme_app_background_pattern', 'none'),
            // App-wide shell variant. `default` keeps the existing top-nav
            // AppLayout; `workspace` swaps to a left vertical rail with the
            // logo, nav icons and the user menu stacked along the side.
            // SPA reads it via theme.data.app.shell_variant.
            'shell_variant' => (string) $this->settings->get('theme_app_shell_variant', 'default'),
            // Workspace-only — rail width in pixels. SPA applies this as an
            // inline width on `<aside.workspace-rail>` and as the matching
            // left padding on the main content. Range 60..120 (clamped via
            // SaveThemeRequest) — 60 is icon-only, 120 fits short labels.
            'rail_width' => (int) $this->settings->get('theme_workspace_rail_width', '72'),
        ];
    }
}
