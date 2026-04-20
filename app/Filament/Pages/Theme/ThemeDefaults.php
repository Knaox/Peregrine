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
    ];

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
