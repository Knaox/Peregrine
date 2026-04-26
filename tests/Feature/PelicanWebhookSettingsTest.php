<?php

namespace Tests\Feature;

use App\Filament\Pages\PelicanWebhookSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Locks the standalone Pelican webhook settings page contract :
 *  - Toggle persists `pelican_webhook_enabled`
 *  - Token is encrypted at rest under `pelican_webhook_token`
 *  - Empty token field keeps the existing value (rotation-safe)
 *  - Saving a new token clears the legacy `bridge_pelican_webhook_token`
 *    fallback so VerifyPelicanWebhookToken never reads stale data
 */
class PelicanWebhookSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsService::class)->clearCache();
    }

    public function test_toggle_persists_enabled_state(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(PelicanWebhookSettings::class)
            ->set('pelican_webhook_enabled', true)
            ->call('save');

        $this->assertSame('true', Setting::where('key', 'pelican_webhook_enabled')->value('value'));
    }

    public function test_token_is_encrypted_at_rest(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(PelicanWebhookSettings::class)
            ->set('pelican_webhook_enabled', true)
            ->set('pelican_webhook_token', 'this-is-a-very-long-secret-token-1234567890')
            ->call('save');

        $stored = Setting::where('key', 'pelican_webhook_token')->value('value');
        $this->assertNotEmpty($stored);
        $this->assertNotSame('this-is-a-very-long-secret-token-1234567890', $stored);
        $this->assertSame('this-is-a-very-long-secret-token-1234567890', Crypt::decryptString($stored));
    }

    public function test_blank_token_keeps_existing_value(): void
    {
        Setting::updateOrCreate(
            ['key' => 'pelican_webhook_token'],
            ['value' => Crypt::encryptString('existing-token-please-keep-me-1234567890')],
        );

        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(PelicanWebhookSettings::class)
            ->set('pelican_webhook_enabled', true)
            ->set('pelican_webhook_token', '')
            ->call('save');

        $stored = Setting::where('key', 'pelican_webhook_token')->value('value');
        $this->assertSame('existing-token-please-keep-me-1234567890', Crypt::decryptString($stored));
    }

    public function test_saving_new_token_clears_legacy_bridge_setting(): void
    {
        // Rotation safety : if the admin types a new token, the legacy
        // Bridge-coupled fallback must be wiped so the middleware can't
        // accidentally accept the old value.
        Setting::updateOrCreate(
            ['key' => 'bridge_pelican_webhook_token'],
            ['value' => Crypt::encryptString('legacy-token-must-be-cleared-1234567890')],
        );

        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(PelicanWebhookSettings::class)
            ->set('pelican_webhook_enabled', true)
            ->set('pelican_webhook_token', 'fresh-token-please-keep-it-long-enough-1234567890')
            ->call('save');

        $this->assertNull(Setting::where('key', 'bridge_pelican_webhook_token')->value('value'));
    }
}
