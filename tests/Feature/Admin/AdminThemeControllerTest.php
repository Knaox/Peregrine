<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Theme\ThemeDefaults;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Smoke tests for the Theme Studio backend. Each endpoint gets a happy-path
 * + at least one 422 / 403 / 401 case so a regression in routing, middleware,
 * or validation surfaces immediately. Heavy property-level coverage of every
 * `theme_*` field belongs in unit tests for ThemeDefaults / SaveThemeRequest;
 * here we focus on the controller surface.
 */
class AdminThemeControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function regularUser(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    public function test_state_requires_authentication(): void
    {
        $this->getJson('/api/admin/theme/state')->assertStatus(401);
    }

    public function test_state_forbids_non_admin(): void
    {
        $this->actingAs($this->regularUser())
            ->getJson('/api/admin/theme/state')
            ->assertStatus(403);
    }

    public function test_state_returns_all_default_keys_for_admin(): void
    {
        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/theme/state')
            ->assertOk();

        $draft = $response->json('draft');
        $this->assertIsArray($draft);
        foreach (array_keys(ThemeDefaults::COLORS) as $key) {
            $this->assertArrayHasKey($key, $draft, "missing key {$key} in state.draft");
        }
        $this->assertArrayHasKey('theme_footer_links', $draft);
        $response->assertJsonStructure(['draft', 'card_config', 'sidebar_config']);
    }

    public function test_presets_returns_brand_presets_for_admin(): void
    {
        $response = $this->actingAs($this->admin())
            ->getJson('/api/admin/theme/presets')
            ->assertOk();

        $presets = $response->json('presets');
        $this->assertIsArray($presets);
        $this->assertNotEmpty($presets);
    }

    public function test_save_persists_settings_and_returns_resolved_theme(): void
    {
        $payload = [
            'theme_primary' => '#ff5500',
            'theme_radius' => '0.75rem',
            'theme_layout_header_sticky' => true,
            'theme_login_carousel_enabled' => false,
            'theme_login_background_images' => ['/storage/branding/login_background/abc.png'],
        ];

        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/save', $payload)
            ->assertOk()
            ->assertJsonStructure(['data', 'css_variables', 'mode_variants', 'card_config', 'sidebar_config']);

        $this->assertSame('#ff5500', Setting::where('key', 'theme_primary')->value('value'));
        $this->assertSame('0.75rem', Setting::where('key', 'theme_radius')->value('value'));
        $this->assertSame('1', Setting::where('key', 'theme_layout_header_sticky')->value('value'));
        $this->assertSame('0', Setting::where('key', 'theme_login_carousel_enabled')->value('value'));

        $imagesRaw = Setting::where('key', 'theme_login_background_images')->value('value');
        $this->assertSame(
            ['/storage/branding/login_background/abc.png'],
            json_decode($imagesRaw, true),
        );
    }

    public function test_save_rejects_invalid_hex_color(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/save', ['theme_primary' => 'not-a-color'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme_primary');
    }

    public function test_save_rejects_invalid_density_enum(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/save', ['theme_density' => 'wat'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme_density');
    }

    public function test_save_rejects_custom_css_with_at_import(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/save', [
                'theme_custom_css' => '@import url("https://evil.tld/x.css");',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme_custom_css');
    }

    public function test_save_rejects_custom_css_with_external_url(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/save', [
                'theme_custom_css' => 'body { background: url("https://evil.tld/x.png"); }',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('theme_custom_css');
    }

    public function test_save_accepts_safe_custom_css(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/save', [
                'theme_custom_css' => '.my-class { color: red; padding: 4px; }',
            ])
            ->assertOk();

        $this->assertSame(
            '.my-class { color: red; padding: 4px; }',
            Setting::where('key', 'theme_custom_css')->value('value'),
        );
    }

    public function test_reset_restores_defaults_and_purges_card_sidebar_config(): void
    {
        // updateOrCreate because the seed_theme_v3_defaults migration
        // already inserted theme_primary with its factory default.
        Setting::updateOrCreate(['key' => 'theme_primary'], ['value' => '#000000']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/reset')
            ->assertOk();

        $this->assertSame(
            ThemeDefaults::COLORS['theme_primary'],
            Setting::where('key', 'theme_primary')->value('value'),
        );
        $this->assertSame('classic', Setting::where('key', 'sidebar_preset')->value('value'));
        $this->assertNotNull(Setting::where('key', 'card_server_config')->value('value'));
    }

    public function test_upload_asset_stores_under_branding_slot_directory(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/upload-asset', [
                'slot' => 'login_background',
                'file' => UploadedFile::fake()->image('bg.png', 800, 600),
            ])
            ->assertOk()
            ->assertJsonStructure(['path', 'url']);

        $publicPath = $response->json('path');
        $this->assertStringStartsWith('/storage/branding/login_background/', $publicPath);

        $relative = ltrim(str_replace('/storage/', '', $publicPath), '/');
        Storage::disk('public')->assertExists($relative);
    }

    public function test_upload_asset_rejects_unknown_slot(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/upload-asset', [
                'slot' => 'totally_made_up',
                'file' => UploadedFile::fake()->image('bg.png'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slot');
    }

    public function test_upload_asset_rejects_oversized_file(): void
    {
        Storage::fake('public');

        // Limit is 5120 KB → 6 MB image must be rejected.
        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/upload-asset', [
                'slot' => 'login_background',
                'file' => UploadedFile::fake()->create('huge.png', 6144, 'image/png'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_upload_asset_rejects_wrong_mime(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/upload-asset', [
                'slot' => 'login_background',
                'file' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_save_forbids_non_admin(): void
    {
        $this->actingAs($this->regularUser())
            ->postJson('/api/admin/theme/save', ['theme_primary' => '#ff0000'])
            ->assertStatus(403);
    }

    public function test_reset_forbids_non_admin(): void
    {
        $this->actingAs($this->regularUser())
            ->postJson('/api/admin/theme/reset')
            ->assertStatus(403);
    }

    public function test_upload_forbids_non_admin(): void
    {
        Storage::fake('public');

        $this->actingAs($this->regularUser())
            ->postJson('/api/admin/theme/upload-asset', [
                'slot' => 'login_background',
                'file' => UploadedFile::fake()->image('bg.png'),
            ])
            ->assertStatus(403);
    }

    public function test_state_returns_revision(): void
    {
        Setting::updateOrCreate(['key' => 'theme_revision'], ['value' => '7']);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/theme/state')
            ->assertOk()
            ->assertJsonPath('revision', 7);
    }

    public function test_save_increments_revision_on_success(): void
    {
        Setting::updateOrCreate(['key' => 'theme_revision'], ['value' => '3']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/save', [
                'theme_primary' => '#abcdef',
                'expected_revision' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('revision', 4);

        $this->assertSame('4', Setting::where('key', 'theme_revision')->value('value'));
    }

    public function test_save_returns_409_when_revision_stale(): void
    {
        Setting::updateOrCreate(['key' => 'theme_revision'], ['value' => '5']);

        $response = $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/save', [
                'theme_primary' => '#abcdef',
                'expected_revision' => 3,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('error', 'theme.stale_revision')
            ->assertJsonPath('current_revision', 5)
            ->assertJsonPath('your_revision', 3);

        // Stale request must NOT have applied changes nor bumped the revision.
        $this->assertSame('5', Setting::where('key', 'theme_revision')->value('value'));
        $this->assertNotSame('#abcdef', Setting::where('key', 'theme_primary')->value('value'));
    }

    public function test_save_without_expected_revision_skips_lock_check(): void
    {
        // CLI imports / scripts that don't carry expected_revision should
        // still be able to save (last-write-wins by design for non-UI paths).
        Setting::updateOrCreate(['key' => 'theme_revision'], ['value' => '5']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/save', ['theme_primary' => '#abcdef'])
            ->assertOk();

        $this->assertSame('#abcdef', Setting::where('key', 'theme_primary')->value('value'));
        // Revision still bumps so other observers detect the change.
        $this->assertSame('6', Setting::where('key', 'theme_revision')->value('value'));
    }

    public function test_reset_bumps_revision(): void
    {
        Setting::updateOrCreate(['key' => 'theme_revision'], ['value' => '10']);

        $this->actingAs($this->admin())
            ->postJson('/api/admin/theme/reset')
            ->assertOk()
            ->assertJsonPath('revision', 11);
    }
}
