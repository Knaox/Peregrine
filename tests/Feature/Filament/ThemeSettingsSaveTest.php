<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Theme\ThemeDefaults;
use App\Filament\Pages\ThemeSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression test for the Filament ThemeSettings save bug.
 *
 * Bug: ThemeSettings's Filament form schema only declares ~26 of the 53
 * keys in ThemeDefaults::COLORS. The save loop used to do
 * `$settings->set($key, $data[$key] ?? null)` for *every* key, which
 * silently nulled out the 27 Vague 3 settings (layout / sidebar /
 * login templates / per-page / footer / refinements) every time an
 * admin saved from /admin/theme-settings.
 *
 * Concretely: an admin configures Vague 3 via /theme-studio, then later
 * goes to Filament just to tweak a primary color and clicks Save —
 * before this fix, all the Vague 3 work was lost without warning.
 */
class ThemeSettingsSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsService::class)->clearCache();
    }

    public function test_filament_save_does_not_wipe_studio_only_keys(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Pre-seed two Vague 3 keys that the Filament form does NOT expose.
        // These are the keys an admin would have configured via /theme-studio.
        // updateOrCreate because the seed_theme_v3_defaults migration already
        // inserted these keys with their factory defaults.
        Setting::updateOrCreate(['key' => 'theme_layout_header_height'], ['value' => '72']);
        Setting::updateOrCreate(['key' => 'theme_sidebar_floating'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'theme_footer_enabled'], ['value' => '1']);

        // Save through Filament with only fields the form actually exposes
        // — leaving the studio-only keys absent from $data.
        Livewire::actingAs($admin)
            ->test(ThemeSettings::class)
            ->set('theme_primary', '#ff5500')
            ->call('save')
            ->assertHasNoErrors();

        // The Filament-exposed key was persisted.
        $this->assertSame(
            '#ff5500',
            Setting::where('key', 'theme_primary')->value('value'),
            'Filament should persist exposed fields',
        );

        // CRITICAL: the studio-only keys were preserved, not nulled.
        $this->assertSame(
            '72',
            Setting::where('key', 'theme_layout_header_height')->value('value'),
            'Filament save must not wipe theme_layout_header_height',
        );
        $this->assertSame(
            '1',
            Setting::where('key', 'theme_sidebar_floating')->value('value'),
            'Filament save must not wipe theme_sidebar_floating',
        );
        $this->assertSame(
            '1',
            Setting::where('key', 'theme_footer_enabled')->value('value'),
            'Filament save must not wipe theme_footer_enabled',
        );
    }

    public function test_filament_save_persists_exposed_form_fields(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(ThemeSettings::class)
            ->set('theme_primary', '#123456')
            ->set('theme_radius', '0.75rem')
            ->set('theme_density', 'spacious')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('#123456', Setting::where('key', 'theme_primary')->value('value'));
        $this->assertSame('0.75rem', Setting::where('key', 'theme_radius')->value('value'));
        $this->assertSame('spacious', Setting::where('key', 'theme_density')->value('value'));
    }

    public function test_filament_form_schema_does_not_cover_all_vague3_keys(): void
    {
        // This test is documentation: it explicitly proves the design
        // assumption that Filament form ⊂ ThemeDefaults::COLORS, which is
        // *why* the save loop must skip absent keys instead of nulling them.
        $vague3OnlyKeys = [
            'theme_layout_header_height',
            'theme_layout_header_sticky',
            'theme_layout_header_align',
            'theme_layout_container_max',
            'theme_layout_page_padding',
            'theme_sidebar_classic_width',
            'theme_sidebar_rail_width',
            'theme_sidebar_mobile_width',
            'theme_sidebar_blur_intensity',
            'theme_sidebar_floating',
            'theme_login_template',
            'theme_login_background_image',
            'theme_login_background_blur',
            'theme_login_background_pattern',
            'theme_login_carousel_enabled',
            'theme_page_console_fullwidth',
            'theme_page_files_fullwidth',
            'theme_page_dashboard_expanded',
            'theme_footer_enabled',
            'theme_footer_text',
            'theme_animation_speed',
            'theme_hover_scale',
            'theme_border_width',
            'theme_glass_blur_global',
            'theme_font_size_scale',
            'theme_app_background_pattern',
        ];

        foreach ($vague3OnlyKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                ThemeDefaults::COLORS,
                "Expected {$key} to live in ThemeDefaults::COLORS",
            );
        }
    }
}
