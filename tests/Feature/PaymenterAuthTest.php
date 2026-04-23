<?php

namespace Tests\Feature;

use App\Models\OAuthIdentity;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\AuthProviderRegistry;
use App\Services\Auth\MatchResult;
use App\Services\Auth\SocialUserMatcher;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class PaymenterAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Default shape — Paymenter is the active canonical IdP, Shop disabled.
        Setting::updateOrCreate(['key' => 'auth_shop_enabled'], ['value' => 'false']);
        Setting::updateOrCreate(['key' => 'auth_paymenter_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(['key' => 'auth_paymenter_config'], ['value' => json_encode([
            'base_url' => 'https://billing.test',
            'client_id' => 'client',
            'client_secret_encrypted' => '',
            'redirect_uri' => 'https://peregrine.test/api/auth/social/paymenter/callback',
        ])]);
        Setting::updateOrCreate(['key' => 'auth_local_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(['key' => 'auth_providers'], ['value' => json_encode([
            'google' => ['enabled' => false],
            'discord' => ['enabled' => false],
            'linkedin' => ['enabled' => false],
        ])]);
        app(SettingsService::class)->clearCache();
    }

    public function test_registry_reports_paymenter_as_canonical_when_enabled(): void
    {
        $this->assertSame('paymenter', app(AuthProviderRegistry::class)->canonicalProvider());
        $this->assertTrue(app(AuthProviderRegistry::class)->isCanonical('paymenter'));
        $this->assertFalse(app(AuthProviderRegistry::class)->isCanonical('google'));
    }

    public function test_registry_configures_socialite_with_derived_urls(): void
    {
        app(AuthProviderRegistry::class)->configureSocialite('paymenter');

        $cfg = config('services.paymenter');
        $this->assertSame('https://billing.test/oauth/authorize', $cfg['authorize_url']);
        $this->assertSame('https://billing.test/api/oauth/token', $cfg['token_url']);
        $this->assertSame('https://billing.test/api/me', $cfg['user_url']);
    }

    public function test_matcher_matches_by_verified_email_for_paymenter(): void
    {
        $user = User::factory()->create(['email' => 'billing@example.com']);

        $result = app(SocialUserMatcher::class)
            ->match('paymenter', $this->makeSocialiteUser('42', 'billing@example.com', ['email_verified' => true]));

        $this->assertSame(MatchResult::ACTION_MATCH_BY_EMAIL, $result->action);
        $this->assertSame($user->id, $result->user?->id);
    }

    public function test_matcher_rejects_paymenter_when_email_not_verified(): void
    {
        User::factory()->create(['email' => 'pending@example.com']);

        $result = app(SocialUserMatcher::class)
            ->match('paymenter', $this->makeSocialiteUser('43', 'pending@example.com', ['email_verified' => false]));

        $this->assertSame(MatchResult::ACTION_REJECT_UNVERIFIED_EMAIL, $result->action);
    }

    public function test_matcher_revalidates_paymenter_email_verified_on_identity_hit(): void
    {
        // Identity was linked previously when email was verified — but now the
        // user's Paymenter email verification was revoked. Canonical IdP must
        // re-gate on every login (mirrors Shop behaviour).
        $user = User::factory()->create();
        OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'paymenter',
            'provider_user_id' => '99',
            'provider_email' => $user->email,
        ]);

        $result = app(SocialUserMatcher::class)
            ->match('paymenter', $this->makeSocialiteUser('99', $user->email, ['email_verified' => false]));

        $this->assertSame(MatchResult::ACTION_REJECT_UNVERIFIED_EMAIL, $result->action);
    }

    public function test_matcher_allows_creation_via_canonical_paymenter(): void
    {
        // Canonical mode: Paymenter ITSELF can create new users (it IS the
        // sign-up channel), social providers cannot.
        $result = app(SocialUserMatcher::class)
            ->match('paymenter', $this->makeSocialiteUser('new1', 'fresh@billing.test', ['email_verified' => true]));

        $this->assertSame(MatchResult::ACTION_CREATE, $result->action);
    }

    public function test_matcher_rejects_google_signup_when_paymenter_canonical(): void
    {
        // Google is a sign-IN method, never sign-UP, when a canonical IdP is
        // active — same rule as Shop.
        $result = app(SocialUserMatcher::class)
            ->match('google', $this->makeSocialiteUser('g1', 'fresh@billing.test', ['email_verified' => true]));

        $this->assertSame(MatchResult::ACTION_REJECT_REGISTER_ON_SHOP_FIRST, $result->action);
    }

    public function test_list_providers_endpoint_surfaces_paymenter_as_canonical(): void
    {
        Setting::updateOrCreate(['key' => 'auth_paymenter_config'], ['value' => json_encode([
            'base_url' => 'https://billing.test',
            'client_id' => 'client',
            'client_secret_encrypted' => '',
            'redirect_uri' => 'https://peregrine.test/api/auth/social/paymenter/callback',
            'register_url' => 'https://billing.test/register',
        ])]);
        app(SettingsService::class)->clearCache();

        $response = $this->getJson('/api/auth/providers');

        $response->assertOk();
        $this->assertSame('paymenter', $response->json('canonical_provider'));
        $this->assertSame('https://billing.test/register', $response->json('canonical_register_url'));
        $this->assertSame('paymenter', $response->json('providers.0.id'));
        $this->assertTrue($response->json('providers.0.canonical'));
    }

    /**
     * @param  array<string, mixed>  $extraRaw
     */
    private function makeSocialiteUser(string $id, string $email, array $extraRaw = []): SocialiteUser
    {
        $u = new SocialiteUser();
        $u->map(['id' => $id, 'email' => $email, 'name' => $email]);
        $u->user = array_merge(['id' => $id, 'email' => $email], $extraRaw);

        return $u;
    }
}
