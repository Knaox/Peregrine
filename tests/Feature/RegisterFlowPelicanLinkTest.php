<?php

namespace Tests\Feature;

use App\Jobs\Pelican\LinkPelicanAccountJob;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RegisterFlowPelicanLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_dispatches_link_job(): void
    {
        Setting::updateOrCreate(['key' => 'auth_local_registration_enabled'], ['value' => 'true']);
        Bus::fake();

        $response = $this->withHeader('Referer', config('app.url'))->postJson('/api/auth/register', [
            'name' => 'Damien',
            'email' => 'damien@example.com',
            'password' => 'super-secret-pwd',
            'password_confirmation' => 'super-secret-pwd',
            'locale' => 'fr',
        ]);

        $response->assertCreated();

        Bus::assertDispatched(LinkPelicanAccountJob::class, function ($job) {
            return $job->source === 'register';
        });
    }

    public function test_login_backfill_dispatches_link_job_for_unlinked_user(): void
    {
        Bus::fake();

        \App\Models\User::factory()->create([
            'email' => 'legacy@example.com',
            'password' => bcrypt('legacy-password-123'),
            'pelican_user_id' => null,
        ]);

        $response = $this->withHeader('Referer', config('app.url'))->postJson('/api/auth/login', [
            'email' => 'legacy@example.com',
            'password' => 'legacy-password-123',
        ]);

        $response->assertOk();

        Bus::assertDispatched(LinkPelicanAccountJob::class, function ($job) {
            return $job->source === 'login-backfill';
        });
    }

    public function test_login_does_not_dispatch_link_job_for_already_linked_user(): void
    {
        Bus::fake();

        \App\Models\User::factory()->create([
            'email' => 'linked@example.com',
            'password' => bcrypt('linked-password-123'),
            'pelican_user_id' => 42,
        ]);

        $response = $this->withHeader('Referer', config('app.url'))->postJson('/api/auth/login', [
            'email' => 'linked@example.com',
            'password' => 'linked-password-123',
        ]);

        $response->assertOk();

        Bus::assertNotDispatched(LinkPelicanAccountJob::class);
    }
}
