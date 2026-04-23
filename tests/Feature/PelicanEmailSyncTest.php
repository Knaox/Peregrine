<?php

namespace Tests\Feature;

use App\Services\Pelican\PelicanApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Locks the regression: Pelican's PATCH /users/{id} validates `username` as
 * required even on a partial update. Sending {email} alone returns 422 and
 * the silent catches in SocialAuthService + UserController used to hide that
 * for months. changeUserEmail() must always include username + name.
 */
class PelicanEmailSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_user_email_sends_username_and_name_in_patch(): void
    {
        config()->set('panel.pelican.url', 'https://pelican.test');
        config()->set('panel.pelican.admin_api_key', 'test-key');

        Http::fake([
            'pelican.test/api/application/users/42' => Http::sequence()
                ->push([
                    'object' => 'user',
                    'attributes' => [
                        'id' => 42,
                        'email' => 'old@example.com',
                        'username' => 'olduser',
                        'name' => 'Old Name',
                        'root_admin' => false,
                        'created_at' => '2026-01-01T00:00:00Z',
                    ],
                ], 200)
                ->push([
                    'object' => 'user',
                    'attributes' => [
                        'id' => 42,
                        'email' => 'new@example.com',
                        'username' => 'olduser',
                        'name' => 'Old Name',
                        'root_admin' => false,
                        'created_at' => '2026-01-01T00:00:00Z',
                    ],
                ], 200),
        ]);

        $result = app(PelicanApplicationService::class)->changeUserEmail(42, 'new@example.com');

        $this->assertSame('new@example.com', $result->email);

        Http::assertSent(function ($request) {
            if ($request->method() !== 'PATCH') {
                return false;
            }
            $body = json_decode($request->body(), true);
            return ($body['email'] ?? null) === 'new@example.com'
                && ($body['username'] ?? null) === 'olduser'
                && ($body['name'] ?? null) === 'Old Name';
        });
    }
}
