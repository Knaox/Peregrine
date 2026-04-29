<?php

namespace App\Filament\Pages\Theme;

final class ThemeDefaults
{
    public const COLORS = [
        'theme_preset' => 'orange',
        'theme_mode' => 'dark',
        'theme_primary' => '#f97316',
        'theme_primary_hover' => '#fb923c',
        'theme_secondary' => '#8b5cf6',
        'theme_ring' => '#fb923c',
        'theme_danger' => '#ef4444',
        'theme_warning' => '#f59e0b',
        'theme_success' => '#10b981',
        'theme_info' => '#3b82f6',
        'theme_background' => '#0c0a14',
        'theme_surface' => '#16131e',
        'theme_surface_hover' => '#1e1a2a',
        'theme_surface_elevated' => '#1a1724',
        'theme_border' => '#2a2535',
        'theme_border_hover' => '#3a3445',
        'theme_text_primary' => '#f1f0f5',
        'theme_text_secondary' => '#8b849e',
        'theme_text_muted' => '#5a5370',
        'theme_radius' => '0.75rem',
        'theme_font' => 'Inter',
        'theme_shadow_intensity' => '50',
        'theme_density' => 'comfortable',
        'theme_custom_css' => '',
        // Layout — Vague 3 démarrage. Defaults reproduce the prior hardcoded
        // AppLayout exactly (h-16, sticky, max-w-7xl ≈ 1280, comfortable
        // padding) so existing installs see no visual change after upgrade.
        'theme_layout_header_height' => '64',
        'theme_layout_header_sticky' => '1',
        'theme_layout_header_align' => 'default',
        'theme_layout_container_max' => '1280',
        'theme_layout_page_padding' => 'comfortable',
        // Sidebar in-server avancée (Vague 3 complète). Defaults preserve
        // current LeftSidebar geometry exactly: 224 / 64 / 256 / 12 / off.
        'theme_sidebar_classic_width' => '224',
        'theme_sidebar_rail_width' => '64',
        'theme_sidebar_mobile_width' => '256',
        'theme_sidebar_blur_intensity' => '12',
        'theme_sidebar_floating' => '0',
        // Login templates (Vague 3 complète). centered = current layout.
        'theme_login_template' => 'centered',
        'theme_login_background_image' => '',
        'theme_login_background_blur' => '0',
        'theme_login_background_pattern' => 'gradient',
        // Per-page layout overrides (Vague 3 complète).
        'theme_page_console_fullwidth' => '0',
        'theme_page_files_fullwidth' => '0',
        'theme_page_dashboard_expanded' => '0',
        // Footer (Vague 3 complète). Off by default — zero-regression.
        'theme_footer_enabled' => '0',
        'theme_footer_text' => '',
        // Refinements (Vague 3 complète — "plus de perso"). Defaults match
        // the prior hardcoded values so existing installs see no change.
        'theme_animation_speed' => 'default',
        'theme_hover_scale' => 'default',
        'theme_border_width' => '1',
        'theme_glass_blur_global' => '16',
        'theme_font_size_scale' => 'default',
        // App-wide background pattern (Vague 3 complète). Same enum as
        // login templates — applied behind the AppLayout content.
        'theme_app_background_pattern' => 'none',
    ];

    /** @var array<int, array{label: string, url: string}> */
    public const FOOTER_LINKS = [];

    public const CARD_CONFIG = [
        'layout' => 'grid',
        'columns' => ['desktop' => 3, 'tablet' => 2, 'mobile' => 1],
        'show_egg_icon' => true,
        'show_egg_name' => true,
        'show_plan_name' => true,
        'show_status_badge' => true,
        'show_stats_bars' => true,
        'show_quick_actions' => true,
        'show_ip_port' => false,
        'show_uptime' => false,
        'card_style' => 'glass',
        'sort_default' => 'name',
        'group_by' => 'none',
    ];

    public const SIDEBAR_CONFIG = [
        'position' => 'left',
        'style' => 'default',
        'show_server_status' => true,
        'show_server_name' => true,
        'entries' => [
            ['id' => 'overview', 'label_key' => 'servers.detail.overview', 'icon' => 'home', 'enabled' => true, 'route_suffix' => '', 'order' => 0],
            ['id' => 'console', 'label_key' => 'servers.detail.console', 'icon' => 'terminal', 'enabled' => true, 'route_suffix' => '/console', 'order' => 1],
            ['id' => 'files', 'label_key' => 'servers.detail.files', 'icon' => 'folder', 'enabled' => true, 'route_suffix' => '/files', 'order' => 2],
            ['id' => 'databases', 'label_key' => 'servers.detail.databases', 'icon' => 'database', 'enabled' => true, 'route_suffix' => '/databases', 'order' => 3],
            ['id' => 'backups', 'label_key' => 'servers.detail.backups', 'icon' => 'archive', 'enabled' => true, 'route_suffix' => '/backups', 'order' => 4],
            ['id' => 'schedules', 'label_key' => 'servers.detail.schedules', 'icon' => 'clock', 'enabled' => true, 'route_suffix' => '/schedules', 'order' => 5],
            ['id' => 'network', 'label_key' => 'servers.detail.network', 'icon' => 'globe', 'enabled' => true, 'route_suffix' => '/network', 'order' => 6],
            ['id' => 'sftp', 'label_key' => 'servers.detail.sftp', 'icon' => 'key', 'enabled' => true, 'route_suffix' => '/sftp', 'order' => 7],
        ],
    ];
}
