<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\ThemePresets;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            'app_name' => 'Peregrine',
            'app_logo_path' => '/images/logo.webp',
            'app_favicon_path' => '/images/favicon.ico',
            'theme_preset' => 'orange',
            ...ThemePresets::get('orange'),
            'card_server_config' => json_encode([
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
            ]),
            'sidebar_server_config' => json_encode([
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
            ]),
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
