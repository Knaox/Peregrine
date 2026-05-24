<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Settings\SettingsPersister;
use App\Services\SettingsService;
use App\Services\SetupService;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The "remember me" cookie lifetime is admin-configurable from /admin/settings
 * and stored in the DB (Docker-safe — survives a container restart, never
 * reverts to a baked-in default). These cover the two custom seams:
 *   - the persister clamps + stores the value;
 *   - a remember login honours the configured duration on the recaller cookie.
 */
class RememberMeLifetimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_persist_clamps_and_stores_the_remember_lifetime(): void
    {
        $settings = app(SettingsService::class);
        $persister = new SettingsPersister($settings, $this->createMock(SetupService::class));

        // `default_locale` is always present in the real form payload.
        $form = fn (int $days): array => ['default_locale' => 'en', 'auth_remember_lifetime_days' => $days];

        // Above the ceiling → clamped to 3650.
        $persister->persist($form(9999));
        $this->assertSame('3650', $settings->get('auth_remember_lifetime_days'));

        // Zero/typo → clamped up to the 1-day floor (never disables it).
        $persister->persist($form(0));
        $this->assertSame('1', $settings->get('auth_remember_lifetime_days'));

        // In-range → stored verbatim.
        $persister->persist($form(45));
        $this->assertSame('45', $settings->get('auth_remember_lifetime_days'));
    }

    public function test_remember_login_honours_the_configured_lifetime(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('auth_remember_lifetime_days', '7');

        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        // Mirror what AppServiceProvider::boot() does on an HTTP request (the
        // boot hook is skipped under runningInConsole() during tests).
        $guard = Auth::guard('web');
        $this->assertInstanceOf(SessionGuard::class, $guard);
        $guard->setRememberDuration((int) $settings->get('auth_remember_lifetime_days', 30) * 24 * 60);

        $this->assertTrue($guard->attempt(['email' => $user->email, 'password' => 'secret123'], true));

        $recaller = collect(app('cookie')->getQueuedCookies())
            ->first(fn ($c) => str_starts_with($c->getName(), 'remember_'));

        $this->assertNotNull($recaller, 'A recaller cookie should be queued for a remembered login.');

        $daysOut = ($recaller->getExpiresTime() - time()) / 86400;
        $this->assertGreaterThan(6.5, $daysOut);
        $this->assertLessThan(7.5, $daysOut);
    }
}
