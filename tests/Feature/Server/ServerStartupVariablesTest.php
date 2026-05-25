<?php

declare(strict_types=1);

namespace Tests\Feature\Server;

use App\Models\Egg;
use App\Models\Nest;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the startup-variable endpoints: clearing a value (the 422 regression),
 * the batch update backing the unified save bar, and its partial-success
 * semantics. All Pelican Client API traffic is faked.
 */
class ServerStartupVariablesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('panel.pelican.url', 'https://pelican.test');
        config()->set('panel.pelican.admin_api_key', 'test-admin-key');
        config()->set('panel.pelican.client_api_key', 'test-client-key');

        Cache::flush();
    }

    /**
     * Faked Pelican Client API. The per-variable PUT (`/startup/variable`)
     * returns 204, except when `$failKey` matches the submitted key — then a
     * 500 so the service's `->throw()` fires and the action records the failure.
     */
    private function fakePelican(?string $failKey = null): void
    {
        Http::fake(function (ClientRequest $request) use ($failKey) {
            if (str_contains($request->url(), '/startup/variable') && $request->method() === 'PUT') {
                if ($failKey !== null && ($request['key'] ?? null) === $failKey) {
                    return Http::response(['errors' => [['detail' => 'nope']]], 500);
                }

                return Http::response([], 204);
            }

            return Http::response([], 204);
        });
    }

    private function makeServer(int $ownerId): Server
    {
        $nest = Nest::create(['pelican_nest_id' => mt_rand(1, 999999), 'name' => 'N']);
        $egg = Egg::create([
            'pelican_egg_id' => 555,
            'nest_id' => $nest->id,
            'name' => 'Paper',
            'docker_image' => 'img',
            'startup' => 'java -jar {{SERVER_JARFILE}}',
        ]);

        return Server::create([
            'user_id' => $ownerId,
            'pelican_server_id' => 4242,
            'identifier' => 'srv-uuid',
            'name' => 'mc',
            'status' => 'running',
            'egg_id' => $egg->id,
        ]);
    }

    private function asOwner(Server $server, User $user): void
    {
        DB::table('server_user')->insert([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'permissions' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_clearing_a_variable_saves_an_empty_value(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        // An empty value used to be rejected with 422 (`required`); now `present`
        // accepts it so a variable can be cleared.
        $response = $this->actingAs($owner)
            ->putJson("/api/servers/{$server->id}/startup/variable", ['key' => 'MOTD', 'value' => '']);

        $response->assertOk()->assertJson(['success' => true]);

        Http::assertSent(fn (ClientRequest $r): bool => $r->method() === 'PUT'
            && str_contains($r->url(), '/api/client/servers/srv-uuid/startup/variable')
            && $r['key'] === 'MOTD'
            && $r['value'] === '');
    }

    public function test_batch_update_applies_every_variable(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)->putJson("/api/servers/{$server->id}/startup/variables", [
            'variables' => [
                ['key' => 'MAX_PLAYERS', 'value' => '50'],
                ['key' => 'MOTD', 'value' => ''],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('updated', 2)
            ->assertExactJson(['success' => true, 'updated' => 2, 'errors' => []]);

        Http::assertSent(fn (ClientRequest $r): bool => $r->method() === 'PUT'
            && str_contains($r->url(), '/startup/variable') && $r['key'] === 'MAX_PLAYERS' && $r['value'] === '50');
        Http::assertSent(fn (ClientRequest $r): bool => $r->method() === 'PUT'
            && str_contains($r->url(), '/startup/variable') && $r['key'] === 'MOTD' && $r['value'] === '');
    }

    public function test_batch_update_reports_partial_failure(): void
    {
        $this->fakePelican(failKey: 'FAILS');
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $response = $this->actingAs($owner)->putJson("/api/servers/{$server->id}/startup/variables", [
            'variables' => [
                ['key' => 'OK1', 'value' => 'a'],
                ['key' => 'FAILS', 'value' => 'b'],
            ],
        ]);

        // 200 with partial-success body: the good key applied, the bad one is
        // reported so the front-end keeps it dirty for a retry.
        $response->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('errors.FAILS', 'update_failed');
    }

    public function test_batch_update_requires_a_non_empty_variables_array(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $this->actingAs($owner)
            ->putJson("/api/servers/{$server->id}/startup/variables", ['variables' => []])
            ->assertStatus(422);
    }

    public function test_a_stranger_cannot_batch_update_variables(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $server = $this->makeServer($owner->id);
        $this->asOwner($server, $owner);

        $this->actingAs($stranger)->putJson("/api/servers/{$server->id}/startup/variables", [
            'variables' => [['key' => 'MOTD', 'value' => 'x']],
        ])->assertStatus(403);

        Http::assertNotSent(fn (ClientRequest $r): bool => str_contains($r->url(), '/startup/variable'));
    }
}
