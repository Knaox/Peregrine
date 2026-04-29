<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\CallbackOutcome;
use App\Services\Auth\SocialAuthService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Pins the post-OAuth redirect logic that breaks the consent-prompt
 * loop reported when admin 2FA enforcement is on but the freshly-
 * authenticated admin hasn't set up 2FA yet.
 *
 * Without the fix : OAuth callback → /dashboard → admin clicks /admin
 * → Filament canAccessPanel returns false → 403 / bounce to /login →
 * admin re-clicks "Login with Shop" → fresh OAuth consent prompt →
 * loop, because the consent grant doesn't help with the missing TOTP.
 *
 * With the fix : OAuth callback notices the enforcement-but-no-2FA
 * state and redirects directly to /2fa/setup?enforced=1, where the
 * admin can actually unblock themselves.
 */
class OAuthCallbackTwoFactorEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_oauth_callback_redirects_admin_to_2fa_setup_when_enforcement_is_on_and_user_has_no_2fa(): void
    {
        config(['panel.installed' => true]);
        app(SettingsService::class)->set('auth_2fa_required_admins', 'true');

        $admin = User::factory()->create([
            'is_admin' => true,
            'two_factor_confirmed_at' => null,
        ]);

        $this->mockSocialServiceCallback($admin, requires2fa: false);

        $response = $this->get('/api/auth/social/shop/callback?code=fake&state=fake');

        $response->assertRedirect('/2fa/setup?enforced=1');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_oauth_callback_redirects_admin_to_dashboard_when_user_already_has_2fa(): void
    {
        config(['panel.installed' => true]);
        app(SettingsService::class)->set('auth_2fa_required_admins', 'true');

        $admin = User::factory()->create([
            'is_admin' => true,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->mockSocialServiceCallback($admin, requires2fa: false);

        $response = $this->get('/api/auth/social/shop/callback?code=fake&state=fake');

        $response->assertRedirect('/dashboard');
    }

    public function test_oauth_callback_redirects_non_admin_to_dashboard_even_without_2fa(): void
    {
        config(['panel.installed' => true]);
        app(SettingsService::class)->set('auth_2fa_required_admins', 'true');

        $user = User::factory()->create([
            'is_admin' => false,
            'two_factor_confirmed_at' => null,
        ]);

        $this->mockSocialServiceCallback($user, requires2fa: false);

        $response = $this->get('/api/auth/social/shop/callback?code=fake&state=fake');

        // Non-admins aren't blocked by Filament — sending them to /2fa/setup
        // would be a usability regression. They land on /dashboard like
        // before.
        $response->assertRedirect('/dashboard');
    }

    public function test_oauth_callback_redirects_admin_to_dashboard_when_enforcement_is_off(): void
    {
        config(['panel.installed' => true]);
        app(SettingsService::class)->set('auth_2fa_required_admins', 'false');

        $admin = User::factory()->create([
            'is_admin' => true,
            'two_factor_confirmed_at' => null,
        ]);

        $this->mockSocialServiceCallback($admin, requires2fa: false);

        $response = $this->get('/api/auth/social/shop/callback?code=fake&state=fake');

        // No enforcement → no setup detour, even without 2FA.
        $response->assertRedirect('/dashboard');
    }

    /**
     * Bind a mock SocialAuthService that bypasses the real OAuth round-
     * trip and returns a canned CallbackOutcome. Lets the test focus on
     * the controller's redirect logic without standing up Socialite.
     */
    private function mockSocialServiceCallback(User $user, bool $requires2fa): void
    {
        $mock = Mockery::mock(SocialAuthService::class);
        $mock->shouldReceive('handleCallback')->andReturn(new CallbackOutcome(
            user: $user,
            requires2fa: $requires2fa,
            providerWasJustLinked: false,
        ));
        $this->app->instance(SocialAuthService::class, $mock);
    }
}
