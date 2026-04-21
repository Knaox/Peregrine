<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Auth\TwoFactorChallengeStore;
use App\Services\Auth\TwoFactorService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorService $service;

    private Google2FA $google2fa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TwoFactorService::class);
        $this->google2fa = app(Google2FA::class);
    }

    public function test_setup_returns_secret_qr_and_uri(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/auth/2fa/setup')
            ->assertOk()
            ->assertJsonStructure(['secret', 'qr_svg_base64', 'otpauth_uri']);
    }

    public function test_confirm_activates_2fa_with_valid_code(): void
    {
        $user = User::factory()->create();
        $secret = $this->service->generateSecret($user);
        $code = $this->google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($user)
            ->postJson('/api/auth/2fa/confirm', ['secret' => $secret, 'code' => $code]);

        $response->assertOk()->assertJsonStructure(['recovery_codes']);
        $this->assertCount(8, $response->json('recovery_codes'));

        $user->refresh();
        $this->assertTrue($user->hasTwoFactor());
        $this->assertNotNull($user->getAppAuthenticationSecret());
    }

    public function test_confirm_rejects_invalid_code(): void
    {
        $user = User::factory()->create();
        $secret = $this->service->generateSecret($user);

        $this->actingAs($user)
            ->postJson('/api/auth/2fa/confirm', ['secret' => $secret, 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'auth.2fa.invalid_code']);
    }

    public function test_login_requires_2fa_when_user_has_it(): void
    {
        $user = $this->activate2fa(User::factory()->create([
            'password' => Hash::make('secret123'),
        ]));

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonFragment(['requires_2fa' => true])
            ->assertJsonStructure(['challenge_id']);

        $this->assertGuest();
    }

    public function test_challenge_logs_user_in_with_valid_code(): void
    {
        $user = $this->activate2fa(User::factory()->create());
        $challengeId = app(TwoFactorChallengeStore::class)
            ->put($user->id, ['type' => 'password', 'provider' => null, 'intended_url' => null]);

        $code = $this->google2fa->getCurrentOtp($user->getAppAuthenticationSecret());

        $this->postJson('/api/auth/2fa/challenge', [
            'challenge_id' => $challengeId,
            'code' => $code,
        ])->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_challenge_with_expired_id_returns_410(): void
    {
        $this->postJson('/api/auth/2fa/challenge', [
            'challenge_id' => '00000000-0000-0000-0000-000000000000',
            'code' => '123456',
        ])->assertStatus(410)->assertJsonFragment(['error' => 'auth.2fa.challenge_expired']);
    }

    public function test_recovery_code_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $secret = $this->service->generateSecret($user);
        $code = $this->google2fa->getCurrentOtp($secret);

        $recoveryCodes = $this->service->verifyAndActivate($user, $secret, $code);
        $this->assertCount(8, $recoveryCodes);
        $firstCode = $recoveryCodes[0];

        $user->refresh();

        // 1st consumption succeeds.
        $this->assertTrue($this->service->verifyChallenge($user->fresh(), $firstCode));

        // 2nd consumption of the same recovery code must fail.
        $this->assertFalse($this->service->verifyChallenge($user->fresh(), $firstCode));
    }

    public function test_challenge_consumes_pending_state_after_success(): void
    {
        $user = $this->activate2fa(User::factory()->create());
        $store = app(TwoFactorChallengeStore::class);
        $challengeId = $store->put($user->id, ['type' => 'password', 'provider' => null, 'intended_url' => null]);

        $code = $this->google2fa->getCurrentOtp($user->getAppAuthenticationSecret());

        $this->postJson('/api/auth/2fa/challenge', ['challenge_id' => $challengeId, 'code' => $code])
            ->assertOk();

        $this->assertNull($store->get($challengeId));
    }

    public function test_disable_with_correct_password_turns_off_2fa(): void
    {
        $user = $this->activate2fa(User::factory()->create([
            'password' => Hash::make('secret123'),
        ]));

        $this->actingAs($user)
            ->postJson('/api/auth/2fa/disable', ['password' => 'secret123'])
            ->assertOk();

        $this->assertFalse($user->fresh()->hasTwoFactor());
    }

    public function test_disable_rejects_wrong_password(): void
    {
        $user = $this->activate2fa(User::factory()->create([
            'password' => Hash::make('secret123'),
        ]));

        $this->actingAs($user)
            ->postJson('/api/auth/2fa/disable', ['password' => 'wrong'])
            ->assertStatus(422);

        $this->assertTrue($user->fresh()->hasTwoFactor());
    }

    public function test_regenerate_returns_fresh_codes_and_invalidates_old_ones(): void
    {
        $user = $this->activate2fa(User::factory()->create());
        $originalCodes = $this->service->regenerateRecoveryCodes($user);

        $response = $this->actingAs($user->fresh())
            ->postJson('/api/auth/2fa/recovery-codes/regenerate')
            ->assertOk();

        $newCodes = $response->json('recovery_codes');
        $this->assertCount(8, $newCodes);
        $this->assertNotEquals($originalCodes, $newCodes);

        // Old code no longer works.
        $this->assertFalse($this->service->verifyChallenge($user->fresh(), $originalCodes[0]));
    }

    public function test_admin_without_2fa_blocked_when_enforcement_on(): void
    {
        Setting::updateOrCreate(['key' => 'auth_2fa_required_admins'], ['value' => 'true']);
        app(SettingsService::class)->clearCache('auth_2fa_required_admins');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertFalse($admin->canAccessPanel(
            app(\Filament\Panel::class, ['id' => 'admin']) ?? $this->stubPanel()
        ));
    }

    /**
     * Tiny helper: Filament Panel instantiation in tests is heavy — we only
     * need the method invocation, not the panel's behavior.
     */
    private function stubPanel(): \Filament\Panel
    {
        return new \Filament\Panel();
    }

    /**
     * Activate 2FA on a user in-memory and return the refreshed instance.
     */
    private function activate2fa(User $user): User
    {
        $secret = $this->service->generateSecret($user);
        $code = $this->google2fa->getCurrentOtp($secret);
        $this->service->verifyAndActivate($user, $secret, $code);

        return $user->fresh();
    }
}
