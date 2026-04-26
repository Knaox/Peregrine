<?php

namespace Tests\Feature\Bridge;

use App\Filament\Resources\PelicanWebhookLogResource;
use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pelican webhook logs admin resource visibility :
 *  - Visible only when `pelican_webhook_enabled` === 'true'
 *  - Independent of Bridge mode (the receiver is its own feature now)
 */
class PelicanWebhookLogVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_navigation_is_visible_when_webhook_enabled(): void
    {
        Setting::updateOrCreate(['key' => 'pelican_webhook_enabled'], ['value' => 'true']);
        app(SettingsService::class)->clearCache();

        $this->assertTrue(PelicanWebhookLogResource::shouldRegisterNavigation());
    }

    public function test_navigation_is_hidden_when_webhook_disabled(): void
    {
        Setting::updateOrCreate(['key' => 'pelican_webhook_enabled'], ['value' => 'false']);
        app(SettingsService::class)->clearCache();

        $this->assertFalse(PelicanWebhookLogResource::shouldRegisterNavigation());
    }

    public function test_navigation_is_hidden_by_default(): void
    {
        Setting::where('key', 'pelican_webhook_enabled')->delete();
        app(SettingsService::class)->clearCache();

        $this->assertFalse(PelicanWebhookLogResource::shouldRegisterNavigation());
    }
}
