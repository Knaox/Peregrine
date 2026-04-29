<?php

namespace App\Services\Theme;

/**
 * Default values + merge logic for the dashboard card layout config and
 * the per-server sidebar config. Stored as JSON in two settings rows
 * (`card_server_config`, `sidebar_server_config`) — admins edit them
 * via Filament, the SPA reads them via /api/settings/theme.
 *
 * Pure: no DB access. ThemeService loads the JSON, this class merges
 * with the canonical defaults so missing keys keep working values.
 */
class CardConfigResolver
{
    /**
     * @return array<string, mixed>
     */
    public static function cardDefaults(): array
    {
        return [
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
    }

    /**
     * @return array<string, mixed>
     */
    public static function sidebarDefaults(): array
    {
        return [
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

    /**
     * Decode a JSON string and merge with the given defaults. Returns the
     * defaults unchanged if the JSON is null / invalid.
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function mergeJson(?string $json, array $defaults): array
    {
        if ($json === null || $json === '') {
            return $defaults;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }
}
