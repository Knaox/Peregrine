<?php

namespace Tests\Feature;

use App\Exceptions\Auth\LastLoginMethodException;
use App\Exceptions\Auth\RegisterOnShopFirstException;
use App\Exceptions\Auth\UnverifiedEmailException;
use App\Models\OAuthIdentity;
use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\AuthProviderRegistry;
use App\Services\Auth\MatchResult;
use App\Services\Auth\SocialAuthService;
use App\Services\Auth\SocialUserMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Default shape of the auth settings for these tests — Shop off unless
        // a test opts in, all three social providers off.
        Setting::updateOrCreate(['key' => 'auth_shop_enabled'], ['value' => 'false']);
        Setting::updateOrCreate(['key' => 'auth_local_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(['key' => 'auth_local_registration_enabled'], ['value' => 'true']);
        Setting::updateOrCreate(['key' => 'auth_providers'], ['value' => json_encode([
            'google' => ['enabled' => false, 'client_id' => '', 'client_secret_encrypted' => ''],
            'discord' => ['enabled' => false],
            'linkedin' => ['enabled' => false],
        ])]);
        app(\App\Services\SettingsService::class)->clearCache();
    }

    public function test_matcher_hits_existing_identity_by_provider_tuple(): void
    {
        $user = User::factory()->create();
        OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g123',
            'provider_email' => $user->email,
        ]);

        $result = app(SocialUserMatcher::class)
            ->match('google', $this->makeSocialiteUser('g123', $user->email, ['email_verified' => true]));

        $this->assertSame(MatchResult::ACTION_MATCH_BY_IDENTITY, $result->action);
        $this->assertSame($user->id, $result->user?->id);
    }

    public function test_matcher_matches_existing_user_by_verified_email(): void
    {
        $user = User::factory()->create(['email' => 'match@example.com']);

        $result = app(SocialUserMatcher::class)
            ->match('google', $this->makeSocialiteUser('g999', 'match@example.com', ['email_verified' => true]));

        $this->assertSame(MatchResult::ACTION_MATCH_BY_EMAIL, $result->action);
        $this->assertSame($user->id, $result->user?->id);
    }

    public function test_matcher_rejects_when_email_not_verified_by_provider(): void
    {
        User::factory()->create(['email' => 'sensitive@example.com']);

        $result = app(SocialUserMatcher::class)
            ->match('google', $this->makeSocialiteUser('g777', 'sensitive@example.com', ['email_verified' => false]));

        $this->assertSame(MatchResult::ACTION_REJECT_UNVERIFIED_EMAIL, $result->action);
        $this->assertNull($result->user);
    }

    public function test_matcher_rejects_discord_without_verified_flag(): void
    {
        User::factory()->create(['email' => 'discorduser@example.com']);

        $result = app(SocialUserMatcher::class)
            ->match('discord', $this->makeSocialiteUser('d1', 'discorduser@example.com', ['verified' => false]));

        $this->assertSame(MatchResult::ACTION_REJECT_UNVERIFIED_EMAIL, $result->action);
    }

    public function test_matcher_accepts_shop_without_verified_flag(): void
    {
        // Shop is the canonical identity provider — always trusted.
        User::factory()->create(['email' => 'shopuser@example.com']);

        $result = app(SocialUserMatcher::class)
            ->match('shop', $this->makeSocialiteUser('s1', 'shopuser@example.com', []));

        $this->assertSame(MatchResult::ACTION_MATCH_BY_EMAIL, $result->action);
    }

    public function test_matcher_rejects_social_signup_when_shop_mode_on(): void
    {
        Setting::updateOrCreate(['key' => 'auth_shop_enabled'], ['value' => 'true']);
        app(\App\Services\SettingsService::class)->clearCache();

        $result = app(SocialUserMatcher::class)
            ->match('google', $this->makeSocialiteUser('g111', 'newuser@example.com', ['email_verified' => true]));

        $this->assertSame(MatchResult::ACTION_REJECT_REGISTER_ON_SHOP_FIRST, $result->action);
    }

    public function test_matcher_allows_creation_in_standalone_mode(): void
    {
        $result = app(SocialUserMatcher::class)
            ->match('google', $this->makeSocialiteUser('g222', 'fresh@example.com', ['email_verified' => true]));

        $this->assertSame(MatchResult::ACTION_CREATE, $result->action);
    }

    public function test_unlink_blocks_when_last_login_method(): void
    {
        $user = User::factory()->create(['password' => null]);
        OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g1',
            'provider_email' => $user->email,
        ]);

        $this->expectException(LastLoginMethodException::class);

        app(SocialAuthService::class)->unlink($user, 'google', Request::create('/unlink', 'DELETE'));
    }

    public function test_unlink_blocks_when_password_is_empty_string(): void
    {
        // Legacy OAuth users historically had password=''. The Étape A
        // migration normalises those to NULL, but we still want the unlink
        // guard to refuse if a raw '' somehow lands in the column. Bypass
        // the `hashed` cast with a direct DB::update, mirroring how the bad
        // state used to arrive.
        $user = User::factory()->create();
        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->update(['password' => '']);
        OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g1',
            'provider_email' => $user->email,
        ]);

        $this->expectException(LastLoginMethodException::class);

        app(SocialAuthService::class)->unlink($user->fresh(), 'google', Request::create('/unlink', 'DELETE'));
    }

    public function test_unlink_allowed_when_other_identity_exists(): void
    {
        $user = User::factory()->create(['password' => null]);
        OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g1',
            'provider_email' => $user->email,
        ]);
        OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'discord',
            'provider_user_id' => 'd1',
            'provider_email' => $user->email,
        ]);

        app(SocialAuthService::class)->unlink($user, 'google', Request::create('/unlink', 'DELETE'));

        $this->assertSame(1, $user->oauthIdentities()->count());
    }

    public function test_unlink_allowed_when_user_has_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('x')]);
        OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g1',
            'provider_email' => $user->email,
        ]);

        app(SocialAuthService::class)->unlink($user, 'google', Request::create('/unlink', 'DELETE'));

        $this->assertSame(0, $user->oauthIdentities()->count());
    }

    public function test_provider_has_exclusive_users_counts_correctly(): void
    {
        // One user: password null, only google → exclusive.
        $u1 = User::factory()->create(['password' => null]);
        OAuthIdentity::create([
            'user_id' => $u1->id,
            'provider' => 'google',
            'provider_user_id' => 'g-exclusive',
            'provider_email' => $u1->email,
        ]);

        // Another user: password null, google + discord → not exclusive.
        $u2 = User::factory()->create(['password' => null]);
        OAuthIdentity::create([
            'user_id' => $u2->id,
            'provider' => 'google',
            'provider_user_id' => 'g-multi',
            'provider_email' => $u2->email,
        ]);
        OAuthIdentity::create([
            'user_id' => $u2->id,
            'provider' => 'discord',
            'provider_user_id' => 'd-multi',
            'provider_email' => $u2->email,
        ]);

        // Another user: password set, only google → not exclusive.
        $u3 = User::factory()->create(['password' => bcrypt('x')]);
        OAuthIdentity::create([
            'user_id' => $u3->id,
            'provider' => 'google',
            'provider_user_id' => 'g-has-pw',
            'provider_email' => $u3->email,
        ]);

        $this->assertSame(1, app(AuthProviderRegistry::class)->providerHasExclusiveUsers('google'));
    }

    public function test_list_providers_endpoint_returns_secrets_free_data(): void
    {
        Setting::updateOrCreate(['key' => 'auth_providers'], ['value' => json_encode([
            'google' => ['enabled' => true, 'client_id' => 'gid', 'client_secret_encrypted' => 'envelope'],
            'discord' => ['enabled' => false],
            'linkedin' => ['enabled' => false],
        ])]);
        app(\App\Services\SettingsService::class)->clearCache();

        $response = $this->getJson('/api/auth/providers');

        $response->assertOk();
        $payload = $response->json();
        $this->assertTrue($payload['local_enabled']);
        $this->assertCount(1, $payload['providers']);
        $this->assertSame('google', $payload['providers'][0]['id']);
        // No secrets must leak through this endpoint.
        $this->assertStringNotContainsString('envelope', $response->getContent() ?: '');
        $this->assertStringNotContainsString('client_secret', $response->getContent() ?: '');
    }

    public function test_list_identities_reports_can_unlink_any(): void
    {
        $user = User::factory()->create(['password' => null]);
        OAuthIdentity::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'g1',
            'provider_email' => $user->email,
        ]);

        $response = $this->actingAs($user)->getJson('/api/auth/identities');

        $response->assertOk();
        $this->assertFalse($response->json('can_unlink_any'));
    }

    public function test_exception_renders_to_mapped_status(): void
    {
        $resp = (new UnverifiedEmailException())->render(Request::create('/x'));
        $this->assertSame(422, $resp->status());

        $resp = (new RegisterOnShopFirstException())->render(Request::create('/x'));
        $this->assertSame(403, $resp->status());

        $resp = (new LastLoginMethodException())->render(Request::create('/x'));
        $this->assertSame(422, $resp->status());
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
