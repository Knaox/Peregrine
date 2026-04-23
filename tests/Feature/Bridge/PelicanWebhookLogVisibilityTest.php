<?php

namespace Tests\Feature\Bridge;

use App\Enums\BridgeMode;
use App\Filament\Resources\PelicanWebhookLogResource;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 4 — Pelican webhook logs admin resource visibility :
 *  - Visible only when bridge_mode === paymenter
 *  - Hidden in shop_stripe and disabled modes
 */
class PelicanWebhookLogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_is_visible_in_paymenter_mode(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::Paymenter->value]);
        app(SettingsService::class)->clearCache();

        $this->assertTrue(PelicanWebhookLogResource::shouldRegisterNavigation());
    }

    public function test_navigation_is_hidden_in_shop_stripe_mode(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::ShopStripe->value]);
        app(SettingsService::class)->clearCache();

        $this->assertFalse(PelicanWebhookLogResource::shouldRegisterNavigation());
    }

    public function test_navigation_is_hidden_in_disabled_mode(): void
    {
        Setting::updateOrCreate(['key' => 'bridge_mode'], ['value' => BridgeMode::Disabled->value]);
        app(SettingsService::class)->clearCache();

        $this->assertFalse(PelicanWebhookLogResource::shouldRegisterNavigation());
    }
}
