<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Wings\NodeHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ServerNodeStatusTest extends TestCase
{
    use RefreshDatabase;

    private const WINGS = 'http://node1.test:8080';

    private function makeOwnedServer(User $owner, array $overrides = []): Server
    {
        $server = Server::create(array_merge([
            'user_id' => $owner->id,
            'pelican_server_id' => 42,
            'identifier' => 'abc12345',
            'name' => 'Test server',
            'status' => 'active',
        ], $overrides));
        $server->accessUsers()->attach($owner->id, ['role' => 'owner']);

        return $server;
    }

    private function makeNode(): Node
    {
        return Node::create([
            'pelican_node_id' => 5,
            'name' => 'node-fr-1',
            'fqdn' => 'node1.test',
            'scheme' => 'http',
            'daemon_listen' => 8080,
            'daemon_token' => 'valid-token',
            'memory' => 32768,
            'disk' => 512000,
            'location' => 'eu-west',
        ]);
    }

    public function test_owner_sees_node_and_health(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
            self::WINGS.'/api/servers/uuid-1/files/list-directory*' => Http::response(['data' => []], 200),
            self::WINGS.'/api/servers/uuid-1' => Http::response(['state' => 'running'], 200),
        ]);
        $owner = User::factory()->create();
        $node = $this->makeNode();
        $server = $this->makeOwnedServer($owner, ['node_id' => $node->id, 'pelican_uuid' => 'uuid-1']);

        $response = $this->actingAs($owner)->getJson("/api/servers/{$server->id}/node-status");

        $response->assertOk()
            ->assertJsonPath('node.name', 'node-fr-1')
            ->assertJsonPath('health.status', 'healthy')
            ->assertJsonPath('health.severity', 'ok')
            ->assertJsonMissingPath('health.detail');
    }

    public function test_wings_file_errors_surface_as_server_errors(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
            self::WINGS.'/api/servers/uuid-1/files/list-directory*' => Http::response([
                'error' => 'An unexpected error was encountered while processing this request',
            ], 500),
            self::WINGS.'/api/servers/uuid-1' => Http::response(['state' => 'running'], 200),
        ]);
        $owner = User::factory()->create();
        $node = $this->makeNode();
        $server = $this->makeOwnedServer($owner, ['node_id' => $node->id, 'pelican_uuid' => 'uuid-1']);

        $response = $this->actingAs($owner)->getJson("/api/servers/{$server->id}/node-status");

        $response->assertOk()
            ->assertJsonPath('health.status', 'server_errors')
            ->assertJsonPath('health.severity', 'critical');
    }

    public function test_admin_gets_technical_detail_players_do_not(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => fn () => throw new ConnectionException('cURL error 7: refused'),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $node = $this->makeNode();
        $server = $this->makeOwnedServer($owner, ['node_id' => $node->id, 'pelican_uuid' => 'uuid-1']);

        $response = $this->actingAs($admin)->getJson("/api/servers/{$server->id}/node-status");

        $response->assertOk()
            ->assertJsonPath('health.status', 'unreachable')
            ->assertJsonPath('health.detail', fn ($detail) => str_contains((string) $detail, 'cURL error 7'));
    }

    public function test_user_without_access_gets_403(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $server = $this->makeOwnedServer($owner);

        $this->actingAs($stranger)
            ->getJson("/api/servers/{$server->id}/node-status")
            ->assertForbidden();
    }

    public function test_unprovisioned_server_reports_unknown_without_node(): void
    {
        Http::fake();
        $owner = User::factory()->create();
        $server = $this->makeOwnedServer($owner, ['pelican_server_id' => null]);

        $response = $this->actingAs($owner)->getJson("/api/servers/{$server->id}/node-status");

        $response->assertOk()
            ->assertJsonPath('node', null)
            ->assertJsonPath('health.status', 'unknown');
    }

    public function test_health_probe_crash_degrades_to_unknown_with_node_still_visible(): void
    {
        // The node name is permanent UI (hero chip + info card): an internal
        // crash in the health layer must never 500 the endpoint nor blank
        // the node — it degrades to `unknown`, which renders no banner.
        $this->mock(NodeHealthService::class)
            ->shouldReceive('checkServerOnNode')
            ->andThrow(new \RuntimeException('cache backend exploded'));

        $owner = User::factory()->create();
        $node = $this->makeNode();
        $server = $this->makeOwnedServer($owner, ['node_id' => $node->id, 'pelican_uuid' => 'uuid-1']);

        $response = $this->actingAs($owner)->getJson("/api/servers/{$server->id}/node-status");

        $response->assertOk()
            ->assertJsonPath('node.name', 'node-fr-1')
            ->assertJsonPath('health.status', 'unknown')
            ->assertJsonMissingPath('health.detail');
    }

    public function test_probe_crash_detail_is_admin_only(): void
    {
        $this->mock(NodeHealthService::class)
            ->shouldReceive('checkServerOnNode')
            ->andThrow(new \RuntimeException('cache backend exploded'));

        $admin = User::factory()->create(['is_admin' => true]);
        $owner = User::factory()->create();
        $node = $this->makeNode();
        $server = $this->makeOwnedServer($owner, ['node_id' => $node->id, 'pelican_uuid' => 'uuid-1']);

        $this->actingAs($admin)
            ->getJson("/api/servers/{$server->id}/node-status")
            ->assertOk()
            ->assertJsonPath('health.status', 'unknown')
            ->assertJsonPath('health.detail', 'cache backend exploded');
    }
}
