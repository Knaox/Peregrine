<?php

namespace App\Services\Theme;

use App\Filament\Pages\Theme\ThemeDefaults;

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
     * Single source of truth: pulled from ThemeDefaults so every Vague 3+
     * field (card_header_style, card_density, card_layout_variant…) is
     * present in the merged result even when card_server_config JSON in
     * DB still lacks that key (fresh install, pre-upgrade install, or a
     * partial save). Without this, the SPA gets `card_header_style:
     * undefined` and ServerCard silently skips the egg banner.
     *
     * @return array<string, mixed>
     */
    public static function cardDefaults(): array
    {
        return ThemeDefaults::CARD_CONFIG;
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
