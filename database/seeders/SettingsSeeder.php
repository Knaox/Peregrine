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
        $settings = [
            ['key' => 'app_name', 'value' => 'Peregrine'],
            ['key' => 'app_logo_path', 'value' => '/images/logo.svg'],
            ['key' => 'app_favicon_path', 'value' => '/images/favicon.svg'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']],
            );
        }
    }
}
