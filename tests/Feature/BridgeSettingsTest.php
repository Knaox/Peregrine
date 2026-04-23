<?php

namespace Tests\Feature;

use App\Enums\BridgeMode;
use App\Filament\Pages\BridgeSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\Bridge\BridgeModeService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Livewire\Livewire;
use Tests\TestCase;

class BridgeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsService::class)->clearCache();
    }

    public function test_bridge_mode_radio_persists_paymenter_selection(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(BridgeSettings::class)
            ->set('bridge_mode', BridgeMode::Paymenter->value)
            ->call('save');

        $this->assertSame(BridgeMode::Paymenter->value, Setting::where('key', 'bridge_mode')->value('value'));
        // Backward-compat: legacy bridge_enabled flag must reflect that
        // shop_stripe is NOT active.
        $this->assertSame('false', Setting::where('key', 'bridge_enabled')->value('value'));
    }

    public function test_bridge_mode_radio_persists_shop_stripe_selection(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(BridgeSettings::class)
            ->set('bridge_mode', BridgeMode::ShopStripe->value)
            ->call('save');

        $this->assertSame(BridgeMode::ShopStripe->value, Setting::where('key', 'bridge_mode')->value('value'));
        $this->assertSame('true', Setting::where('key', 'bridge_enabled')->value('value'));
    }

    public function test_legacy_bridge_enabled_resolves_to_shop_stripe(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_enabled'], ['value' => 'true']);
        // No bridge_mode row → legacy fallback path.
        Setting::where('key', 'bridge_mode')->delete();
        app(SettingsService::class)->clearCache();

        $this->assertSame(BridgeMode::ShopStripe, app(BridgeModeService::class)->current());
    }

    public function test_legacy_bridge_disabled_resolves_to_disabled(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_enabled'], ['value' => 'false']);
        Setting::where('key', 'bridge_mode')->delete();
        app(SettingsService::class)->clearCache();

        $this->assertSame(BridgeMode::Disabled, app(BridgeModeService::class)->current());
    }

    public function test_pelican_token_is_encrypted_at_rest(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(BridgeSettings::class)
            ->set('bridge_mode', BridgeMode::Paymenter->value)
            ->set('bridge_pelican_webhook_token', 'this-is-a-very-long-secret-token-1234567890')
            ->call('save');

        $stored = Setting::where('key', 'bridge_pelican_webhook_token')->value('value');
        $this->assertNotEmpty($stored);
        $this->assertNotSame('this-is-a-very-long-secret-token-1234567890', $stored);
        $this->assertSame('this-is-a-very-long-secret-token-1234567890', Crypt::decryptString($stored));
    }

    public function test_blank_pelican_token_keeps_existing_value(): void
    {
        Setting::updateOrCreate(
            ['key' => 'bridge_pelican_webhook_token'],
            ['value' => Crypt::encryptString('existing-token-please-keep-me-1234567890')],
        );

        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(BridgeSettings::class)
            ->set('bridge_mode', BridgeMode::Paymenter->value)
            ->set('bridge_pelican_webhook_token', '')
            ->call('save');

        $stored = Setting::where('key', 'bridge_pelican_webhook_token')->value('value');
        $this->assertSame('existing-token-please-keep-me-1234567890', Crypt::decryptString($stored));
    }
}
