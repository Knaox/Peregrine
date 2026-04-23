<?php

namespace Tests\Feature;

use App\Filament\Pages\AuthSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthSettingsMutualExclusivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(['key' => 'auth_shop_enabled'], ['value' => 'false']);
        Setting::updateOrCreate(['key' => 'auth_paymenter_enabled'], ['value' => 'false']);
        Setting::updateOrCreate(['key' => 'auth_local_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(['key' => 'auth_local_registration_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(['key' => 'auth_2fa_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(['key' => 'auth_2fa_required_admins'], ['value' => 'false']);
        Setting::updateOrCreate(['key' => 'auth_providers'], ['value' => json_encode([
            'google' => ['enabled' => false],
            'discord' => ['enabled' => false],
            'linkedin' => ['enabled' => false],
        ])]);
        app(SettingsService::class)->clearCache();
    }

    public function test_save_blocks_when_both_canonical_providers_are_enabled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(AuthSettings::class)
            ->set('auth_shop_enabled', true)
            ->set('auth_shop_authorize_url', 'https://shop.test/oauth/authorize')
            ->set('auth_shop_token_url', 'https://shop.test/oauth/token')
            ->set('auth_shop_user_url', 'https://shop.test/api/user')
            ->set('auth_paymenter_enabled', true)
            ->set('auth_paymenter_base_url', 'https://billing.test')
            ->call('save');

        // Both flags should remain false in the DB — save() returned early.
        $this->assertSame('false', Setting::where('key', 'auth_shop_enabled')->value('value'));
        $this->assertSame('false', Setting::where('key', 'auth_paymenter_enabled')->value('value'));
    }

    public function test_save_persists_paymenter_when_shop_disabled(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(AuthSettings::class)
            ->set('auth_paymenter_enabled', true)
            ->set('auth_paymenter_base_url', 'https://billing.test/')
            ->set('auth_paymenter_client_id', 'pm-client')
            ->set('auth_paymenter_client_secret', 'pm-secret')
            ->call('save');

        $this->assertSame('true', Setting::where('key', 'auth_paymenter_enabled')->value('value'));

        $config = json_decode(Setting::where('key', 'auth_paymenter_config')->value('value'), true);
        $this->assertSame('https://billing.test', $config['base_url']);
        $this->assertSame('pm-client', $config['client_id']);
        $this->assertNotEmpty($config['client_secret_encrypted']);
        $this->assertNotSame('pm-secret', $config['client_secret_encrypted']);
    }
}
