<?php

declare(strict_types=1);

namespace Tests\Feature\Plugins\Invitations;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Plugins\Invitations\Services\PelicanSubuserService;
use Tests\TestCase;

/**
 * Covers PelicanSubuserService::syncSubuser — the idempotent subuser
 * provisioning used by invitation accept().
 *
 * Regression cover for the "accepts but stays pending" bug: accept() used to
 * call createSubuser blindly, which Pelican rejects with a 4xx when the email
 * is already a subuser. Because the call sat inside the accept DB transaction,
 * that exception rolled back accepted_at + the local grant — the invite got
 * stuck "pending" with no permissions. syncSubuser must instead recover from
 * the "already a subuser" conflict by updating, so acceptance always lands;
 * and it must still propagate genuine failures so a broken accept is NOT
 * silently marked complete.
 */
class SyncSubuserTest extends TestCase
{
    protected function setUp(): void
    {
        // Plugin classes aren't in the composer autoload map; register the
        // PSR-4 prefix the way PluginBootstrap does at runtime (idempotent).
        $repoRoot = __DIR__.'/../../../..';
        $loader = require $repoRoot.'/vendor/autoload.php';
        $loader->addPsr4('Plugins\\Invitations\\', $repoRoot.'/plugins/invitations/src/');

        parent::setUp();

        config()->set('panel.pelican.url', 'https://pelican.test');
        config()->set('panel.pelican.client_api_key', 'test-client-key');
    }

    public function test_first_invite_creates_the_subuser(): void
    {
        Http::fake([
            'pelican.test/api/client/servers/srv/users' => Http::response(['attributes' => ['uuid' => 'u-1']], 200),
        ]);

        app(PelicanSubuserService::class)->syncSubuser('srv', 'New@Example.com', ['control.console']);

        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && str_ends_with($req->url(), '/api/client/servers/srv/users')
            && $req['email'] === 'New@Example.com');
    }

    public function test_already_a_subuser_falls_back_to_update(): void
    {
        // create → 400 "already exists" ; list (email lowercased) ; update → 200.
        // The invitee email is mixed-case on purpose: matching the existing
        // subuser must be case-insensitive.
        Http::fake(function ($request) {
            $url = $request->url();
            $method = $request->method();

            if ($method === 'POST' && str_ends_with($url, '/api/client/servers/srv/users')) {
                return Http::response([
                    'errors' => [['detail' => 'A subuser with that email already exists.']],
                ], 400);
            }

            if ($method === 'GET' && str_ends_with($url, '/api/client/servers/srv/users')) {
                return Http::response([
                    'data' => [[
                        'attributes' => [
                            'uuid' => 'u-9',
                            'email' => 'dup@example.com',
                            'permissions' => ['control.console'],
                        ],
                    ]],
                ], 200);
            }

            if ($method === 'POST' && str_contains($url, '/api/client/servers/srv/users/u-9')) {
                return Http::response(['attributes' => ['uuid' => 'u-9']], 200);
            }

            return Http::response([], 200);
        });

        // Must not throw — the invite would otherwise get stuck "pending".
        app(PelicanSubuserService::class)->syncSubuser('srv', 'Dup@Example.com', ['control.console', 'file.read']);

        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && str_contains($req->url(), '/api/client/servers/srv/users/u-9')
            && $req['permissions'] === ['control.console', 'file.read']);
    }

    public function test_hard_failure_propagates(): void
    {
        Http::fake([
            'pelican.test/api/client/servers/srv/users' => Http::response(['error' => 'boom'], 500),
        ]);

        $this->expectException(RequestException::class);

        app(PelicanSubuserService::class)->syncSubuser('srv', 'x@example.com', ['control.console']);
    }
}
