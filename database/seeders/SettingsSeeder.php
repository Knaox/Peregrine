<?php

namespace Database\Seeders;

use App\Models\Setting;
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
            'app_logo_path' => '/images/logo.svg',
            'app_favicon_path' => '/images/favicon.svg',
            'theme_mode' => 'dark',
            'theme_primary' => '#f97316',
            'theme_primary_hover' => '#ea580c',
            'theme_danger' => '#ef4444',
            'theme_warning' => '#f59e0b',
            'theme_success' => '#22c55e',
            'theme_background' => '#0f172a',
            'theme_surface' => '#1e293b',
            'theme_surface_hover' => '#334155',
            'theme_border' => '#334155',
            'theme_text_primary' => '#f8fafc',
            'theme_text_secondary' => '#94a3b8',
            'theme_text_muted' => '#64748b',
            'theme_radius' => '0.75rem',
            'theme_font' => 'Inter',
            'theme_custom_css' => '',
        ];

        foreach ($defaults as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }
}
