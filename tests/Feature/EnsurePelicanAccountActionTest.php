<?php

namespace Tests\Feature;

use App\Actions\Pelican\EnsurePelicanAccountAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EnsurePelicanAccountActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('panel.pelican.url', 'https://pelican.test');
        config()->set('panel.pelican.admin_api_key', 'test-key');
    }

    public function test_short_circuits_when_user_already_linked(): void
    {
        $user = User::factory()->create(['pelican_user_id' => 42]);

        Http::fake();

        app(EnsurePelicanAccountAction::class)->execute($user, 'test');

        Http::assertNothingSent();
        $this->assertSame(42, $user->fresh()->pelican_user_id);
    }

    public function test_links_when_pelican_already_has_user_with_same_email(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'pelican_user_id' => null,
        ]);

        Http::fake([
            'pelican.test/api/application/users*' => Http::response([
                'data' => [[
                    'object' => 'user',
                    'attributes' => [
                        'id' => 17,
                        'email' => 'existing@example.com',
                        'username' => 'existing',
                        'name' => 'Existing User',
                        'root_admin' => false,
                        'created_at' => '2026-01-01T00:00:00Z',
                    ],
                ]],
            ], 200),
        ]);

        app(EnsurePelicanAccountAction::class)->execute($user, 'test');

        $this->assertSame(17, $user->fresh()->pelican_user_id);
        Http::assertSent(fn ($req) => $req->method() === 'GET'
            && str_contains($req->url(), 'filter%5Bemail%5D=existing%40example.com'));
    }

    public function test_creates_pelican_user_when_no_match(): void
    {
        $user = User::factory()->create([
            'name' => 'Damien Test',
            'email' => 'new@example.com',
            'pelican_user_id' => null,
        ]);

        Http::fake([
            'pelican.test/api/application/users?*' => Http::response(['data' => []], 200),
            'pelican.test/api/application/users' => Http::response([
                'object' => 'user',
                'attributes' => [
                    'id' => 99,
                    'email' => 'new@example.com',
                    'username' => 'damien_test',
                    'name' => 'Damien Test',
                    'root_admin' => false,
                    'created_at' => '2026-01-01T00:00:00Z',
                ],
            ], 201),
        ]);

        app(EnsurePelicanAccountAction::class)->execute($user, 'test');

        $this->assertSame(99, $user->fresh()->pelican_user_id);
        Http::assertSent(function ($req) {
            if ($req->method() !== 'POST') {
                return false;
            }
            $body = json_decode($req->body(), true);
            return ($body['email'] ?? null) === 'new@example.com'
                && ($body['username'] ?? null) === 'damien_test'
                && ($body['name'] ?? null) === 'Damien Test';
        });
    }

    public function test_recovers_from_email_race_via_refind(): void
    {
        $user = User::factory()->create([
            'name' => 'Racy',
            'email' => 'race@example.com',
            'pelican_user_id' => null,
        ]);

        Http::fake([
            'pelican.test/api/application/users?*' => Http::sequence()
                ->push(['data' => []], 200)            // first findByEmail: nothing
                ->push(['data' => [[                    // post-422 re-find: now exists
                    'object' => 'user',
                    'attributes' => [
                        'id' => 55,
                        'email' => 'race@example.com',
                        'username' => 'racy',
                        'name' => 'Racy',
                        'root_admin' => false,
                        'created_at' => '2026-01-01T00:00:00Z',
                    ],
                ]]], 200),
            'pelican.test/api/application/users' => Http::response([
                'errors' => [['source' => ['field' => 'email'], 'detail' => 'The email has already been taken.']],
            ], 422),
        ]);

        app(EnsurePelicanAccountAction::class)->execute($user, 'test');

        $this->assertSame(55, $user->fresh()->pelican_user_id);
    }

    public function test_retries_with_random_suffix_on_username_collision(): void
    {
        $user = User::factory()->create([
            'name' => 'John',
            'email' => 'john@example.com',
            'pelican_user_id' => null,
        ]);

        // PelicanHttpClient retries 3 times on failure. The first POST
        // attempt with username=john uses up 3 sequence entries (all 422),
        // then the action retries with a different username and the 4th
        // sequence entry (201) satisfies it.
        $usernameTaken = ['errors' => [['source' => ['field' => 'username'], 'detail' => 'username taken']]];
        $created = [
            'object' => 'user',
            'attributes' => [
                'id' => 200,
                'email' => 'john@example.com',
                'username' => 'john_abc123',
                'name' => 'John',
                'root_admin' => false,
                'created_at' => '2026-01-01T00:00:00Z',
            ],
        ];

        Http::fake([
            'pelican.test/api/application/users?*' => Http::response(['data' => []], 200),
            'pelican.test/api/application/users' => Http::sequence()
                ->push($usernameTaken, 422)
                ->push($usernameTaken, 422)
                ->push($usernameTaken, 422)
                ->push($created, 201),
        ]);

        app(EnsurePelicanAccountAction::class)->execute($user, 'test');

        $this->assertSame(200, $user->fresh()->pelican_user_id);

        $postBodies = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn ($req) => $req->method() === 'POST')
            ->map(fn ($req) => json_decode($req->body(), true))
            ->values()
            ->all();

        $this->assertGreaterThanOrEqual(2, count($postBodies));
        $this->assertSame('john', $postBodies[0]['username']);
        $newUsernameBody = end($postBodies);
        $this->assertNotSame('john', $newUsernameBody['username']);
        $this->assertStringStartsWith('john_', $newUsernameBody['username']);
    }

    public function test_fallback_username_when_name_too_short(): void
    {
        $user = User::factory()->create([
            'name' => 'a',
            'email' => 'tiny@example.com',
            'pelican_user_id' => null,
        ]);

        Http::fake([
            'pelican.test/api/application/users?*' => Http::response(['data' => []], 200),
            'pelican.test/api/application/users' => Http::response([
                'object' => 'user',
                'attributes' => [
                    'id' => 7,
                    'email' => 'tiny@example.com',
                    'username' => 'user_xxxxxxxx',
                    'name' => 'a',
                    'root_admin' => false,
                    'created_at' => '2026-01-01T00:00:00Z',
                ],
            ], 201),
        ]);

        app(EnsurePelicanAccountAction::class)->execute($user, 'test');

        Http::assertSent(function ($req) {
            if ($req->method() !== 'POST') {
                return true;
            }
            $body = json_decode($req->body(), true);
            return str_starts_with((string) ($body['username'] ?? ''), 'user_');
        });
    }

    public function test_throws_when_user_has_no_email(): void
    {
        // The DB enforces email NOT NULL; instantiate without saving so we
        // can exercise the defensive guard at the top of the action.
        $user = new User(['email' => null]);
        $user->id = 999;
        $user->pelican_user_id = null;

        $this->expectException(\RuntimeException::class);
        app(EnsurePelicanAccountAction::class)->execute($user, 'test');
    }
}
