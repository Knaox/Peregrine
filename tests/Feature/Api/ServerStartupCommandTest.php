<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServerStartupCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeServer(User $user, string $role = 'owner', array $permissions = []): Server
    {
        $server = Server::create([
            'user_id' => $user->id,
            'pelican_server_id' => 42,
            'identifier' => 'abc12345',
            'name' => 'Test server',
            'status' => 'active',
        ]);
        $server->accessUsers()->attach($user->id, [
            'role' => $role,
            'permissions' => $role === 'owner' ? null : json_encode($permissions),
        ]);

        return $server;
    }

    private function fakePelican(string $currentStartup = 'java -jar {{SERVER_JARFILE}}'): void
    {
        Http::fake([
            '*/api/application/servers/42/startup*' => Http::response(['attributes' => []], 200),
            '*/api/application/servers/42*' => Http::response([
                'attributes' => [
                    'id' => 42,
                    'egg' => 3,
                    'container' => [
                        'image' => 'ghcr.io/yolks/java_21',
                        'startup_command' => $currentStartup,
                        'environment' => ['SERVER_JARFILE' => 'server.jar'],
                    ],
                ],
            ], 200),
            '*/api/application/eggs/3*' => Http::response([
                'attributes' => [
                    'id' => 3,
                    'startup_commands' => [
                        'Default' => 'java -jar {{SERVER_JARFILE}}',
                        'Aikar flags' => 'java -XX:+UseG1GC -jar {{SERVER_JARFILE}}',
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_owner_reads_current_command_and_egg_options(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        $response = $this->actingAs($owner)->getJson("/api/servers/{$server->id}/startup/command");

        $response->assertOk()
            ->assertJsonPath('data.current', 'java -jar {{SERVER_JARFILE}}')
            ->assertJsonPath('data.current_name', 'Default')
            ->assertJsonPath('data.is_custom', false)
            ->assertJsonPath('data.options.0.name', 'Default')
            ->assertJsonPath('data.options.1.name', 'Aikar flags');
    }

    public function test_admin_customized_command_is_flagged_custom(): void
    {
        $this->fakePelican('java -SpecialAdminTweak -jar {{SERVER_JARFILE}}');
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        $response = $this->actingAs($owner)->getJson("/api/servers/{$server->id}/startup/command");

        $response->assertOk()
            ->assertJsonPath('data.is_custom', true)
            ->assertJsonPath('data.current_name', null);
    }

    public function test_owner_switches_to_an_egg_defined_command(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        $response = $this->actingAs($owner)->putJson("/api/servers/{$server->id}/startup/command", [
            'name' => 'Aikar flags',
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/api/application/servers/42/startup')
            && $request['startup'] === 'java -XX:+UseG1GC -jar {{SERVER_JARFILE}}'
            && $request['egg'] === 3
            && $request['image'] === 'ghcr.io/yolks/java_21');
    }

    public function test_unknown_command_name_is_rejected_with_422(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        $this->actingAs($owner)
            ->putJson("/api/servers/{$server->id}/startup/command", ['name' => 'rm -rf /'])
            ->assertUnprocessable();

        Http::assertNotSent(fn (Request $request) => $request->method() === 'PATCH');
    }

    public function test_subuser_without_permission_cannot_switch(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        $subuser = User::factory()->create();
        $server->accessUsers()->attach($subuser->id, [
            'role' => 'subuser',
            'permissions' => json_encode(['startup.read']),
        ]);

        $this->actingAs($subuser)
            ->putJson("/api/servers/{$server->id}/startup/command", ['name' => 'Default'])
            ->assertForbidden();

        $this->actingAs($subuser)
            ->getJson("/api/servers/{$server->id}/startup/command")
            ->assertOk();
    }

    public function test_subuser_with_permission_can_switch(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        $subuser = User::factory()->create();
        $server->accessUsers()->attach($subuser->id, [
            'role' => 'subuser',
            'permissions' => json_encode(['startup.read', 'startup.update']),
        ]);

        $this->actingAs($subuser)
            ->putJson("/api/servers/{$server->id}/startup/command", ['name' => 'Default'])
            ->assertOk();
    }

    public function test_unprovisioned_server_returns_null_payload(): void
    {
        Http::fake();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);
        $server->forceFill(['pelican_server_id' => null])->save();

        $this->actingAs($owner)
            ->getJson("/api/servers/{$server->id}/startup/command")
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_display_reads_are_cached_and_invalidated_on_switch(): void
    {
        $this->fakePelican();
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        // Two reads → container + egg options each hit Pelican exactly once.
        $this->actingAs($owner)->getJson("/api/servers/{$server->id}/startup/command")->assertOk();
        $this->actingAs($owner)->getJson("/api/servers/{$server->id}/startup/command")->assertOk();
        Http::assertSentCount(2);

        // A switch reads a FRESH container (never the display cache), sends
        // the PATCH, and drops the display cache → next read re-fetches it.
        $this->actingAs($owner)->putJson("/api/servers/{$server->id}/startup/command", ['name' => 'Default'])->assertOk();
        $this->actingAs($owner)->getJson("/api/servers/{$server->id}/startup/command")->assertOk();
        Http::assertSentCount(5);
    }

    public function test_display_read_survives_a_pelican_outage_on_the_last_good_snapshot(): void
    {
        // First round-trip healthy, everything after = Pelican down/throttled.
        Http::fake([
            '*/api/application/servers/42*' => Http::sequence()
                ->push([
                    'attributes' => [
                        'id' => 42,
                        'egg' => 3,
                        'container' => [
                            'image' => 'ghcr.io/yolks/java_21',
                            'startup_command' => 'java -jar {{SERVER_JARFILE}}',
                            'environment' => ['SERVER_JARFILE' => 'server.jar'],
                        ],
                    ],
                ], 200)
                ->whenEmpty(Http::response(['error' => 'upstream down'], 500)),
            '*/api/application/eggs/3*' => Http::sequence()
                ->push([
                    'attributes' => [
                        'id' => 3,
                        'startup_commands' => [
                            'Default' => 'java -jar {{SERVER_JARFILE}}',
                            'Aikar flags' => 'java -XX:+UseG1GC -jar {{SERVER_JARFILE}}',
                        ],
                    ],
                ], 200)
                ->whenEmpty(Http::response(['error' => 'upstream down'], 500)),
        ]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        // Healthy first read seeds the last-good snapshots.
        $this->actingAs($owner)->getJson("/api/servers/{$server->id}/startup/command")->assertOk();

        // The 60s display cache expires, then Pelican goes down / throttles.
        Cache::forget('peregrine:server-startup-context:42');
        Cache::forget('peregrine:egg-startup-commands:3');

        // The card must NOT vanish: the endpoint serves the last snapshot.
        $this->actingAs($owner)
            ->getJson("/api/servers/{$server->id}/startup/command")
            ->assertOk()
            ->assertJsonPath('data.current', 'java -jar {{SERVER_JARFILE}}')
            ->assertJsonPath('data.current_name', 'Default')
            ->assertJsonPath('data.options.1.name', 'Aikar flags');
    }

    public function test_cold_pelican_failure_still_errors(): void
    {
        // No last-good snapshot yet → nothing sane to serve, keep the 500.
        Http::fake(['*' => Http::response(['error' => 'upstream down'], 500)]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        $this->actingAs($owner)
            ->getJson("/api/servers/{$server->id}/startup/command")
            ->assertStatus(500);
    }

    public function test_legacy_pelican_single_startup_falls_back_to_default_option(): void
    {
        Http::fake([
            '*/api/application/servers/42*' => Http::response([
                'attributes' => [
                    'id' => 42,
                    'egg' => 3,
                    'container' => [
                        'image' => 'ghcr.io/yolks/java_21',
                        'startup_command' => 'java -jar {{SERVER_JARFILE}}',
                        'environment' => [],
                    ],
                ],
            ], 200),
            '*/api/application/eggs/3*' => Http::response([
                'attributes' => ['id' => 3, 'startup' => 'java -jar {{SERVER_JARFILE}}'],
            ], 200),
        ]);
        $owner = User::factory()->create();
        $server = $this->makeServer($owner);

        $this->actingAs($owner)
            ->getJson("/api/servers/{$server->id}/startup/command")
            ->assertOk()
            ->assertJsonPath('data.current_name', 'Default')
            ->assertJsonPath('data.options.0.name', 'Default');
    }
}
