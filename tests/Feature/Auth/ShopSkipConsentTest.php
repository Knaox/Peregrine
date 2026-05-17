<?php

namespace Tests\Feature\Auth;

use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * Pins the opt-in `prompt=allow` injection on the Shop authorize URL.
 *
 * Background : miniOrange OAuth Server (WordPress plugin) shows a consent
 * screen on every login on its free tier. The vendor's documented bypass
 * is to send `prompt=allow` as a query parameter — which miniOrange treats
 * as "silent allow". Since miniOrange does NOT persist that consent, the
 * parameter has to be sent on every authorize request.
 *
 * The toggle is scoped strictly to provider='shop' (other providers are
 * unaffected) and OFF by default (existing installs see no change).
 */
class ShopSkipConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureShop();
    }

    public function test_redirect_url_omits_prompt_when_skip_consent_is_explicitly_off(): void
    {
        app(SettingsService::class)->set('auth_shop_skip_consent', 'false');

        $params = $this->followRedirect('shop');

        $this->assertArrayNotHasKey('prompt', $params);
        $this->assertArrayHasKey('state', $params, 'CSRF state must always be generated');
    }

    public function test_redirect_url_omits_prompt_when_setting_is_absent(): void
    {
        // No setting written — the default in SocialAuthService::redirectUrl
        // must resolve to 'false' so legacy installs see no behaviour change.
        $params = $this->followRedirect('shop');

        $this->assertArrayNotHasKey('prompt', $params);
    }

    public function test_redirect_url_appends_prompt_allow_when_skip_consent_is_on(): void
    {
        app(SettingsService::class)->set('auth_shop_skip_consent', 'true');

        $params = $this->followRedirect('shop');

        $this->assertSame('allow', $params['prompt'] ?? null);
        $this->assertArrayHasKey('state', $params, 'CSRF state must still be generated alongside prompt');
        $this->assertSame('test-client-id', $params['client_id'] ?? null);
    }

    public function test_skip_consent_does_not_affect_other_providers(): void
    {
        // Toggle ON for shop, but we call the paymenter redirect — the
        // strict `$provider === 'shop'` guard in SocialAuthService::redirectUrl
        // must short-circuit and leave paymenter's URL untouched.
        app(SettingsService::class)->set('auth_shop_skip_consent', 'true');
        $this->configurePaymenter();
        // Shop must be disabled when Paymenter is the canonical IdP (mutex
        // is enforced at the persist layer ; the service doesn't know).
        app(SettingsService::class)->set('auth_shop_enabled', 'false');

        $params = $this->followRedirect('paymenter');

        $this->assertArrayNotHasKey('prompt', $params);
    }

    private function configureShop(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('auth_shop_enabled', 'true');
        $settings->set('auth_shop_config', json_encode([
            'client_id' => 'test-client-id',
            'client_secret_encrypted' => '',
            'authorize_url' => 'https://shop.example.test/oauth/authorize',
            'token_url' => 'https://shop.example.test/oauth/token',
            'user_url' => 'https://shop.example.test/api/user',
            'redirect_uri' => 'https://peregrine.test/api/auth/social/shop/callback',
        ], JSON_THROW_ON_ERROR));
    }

    private function configurePaymenter(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('auth_paymenter_enabled', 'true');
        $settings->set('auth_paymenter_config', json_encode([
            'client_id' => 'paymenter-client-id',
            'client_secret_encrypted' => '',
            'base_url' => 'https://paymenter.example.test',
            'redirect_uri' => 'https://peregrine.test/api/auth/social/paymenter/callback',
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Hit the public redirect route and parse the resulting authorize URL.
     * Goes through the real controller + middleware stack so the test
     * exercises the same code path a real browser would.
     *
     * @return array<string, string>
     */
    private function followRedirect(string $provider): array
    {
        $response = $this->withoutMiddleware(ThrottleRequests::class)
            ->get("/api/auth/social/{$provider}/redirect");

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');

        parse_str(parse_url($location, PHP_URL_QUERY) ?: '', $params);

        /** @var array<string, string> $params */
        return $params;
    }
}
