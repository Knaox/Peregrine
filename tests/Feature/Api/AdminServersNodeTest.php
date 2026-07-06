<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Wings\NodeHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminServersNodeTest extends TestCase
{
    use RefreshDatabase;

    private const WINGS = 'http://node1.test:8080';

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
        ]);
    }

    private function makeServer(User $owner, array $overrides = []): Server
    {
        return Server::create(array_merge([
            'user_id' => $owner->id,
            'pelican_server_id' => 42,
            'identifier' => 'abc12345',
            'name' => 'Test server',
            'status' => 'active',
        ], $overrides));
    }

    public function test_admin_list_includes_node_with_cached_health(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);
        $node = $this->makeNode();
        $this->makeServer($admin, ['node_id' => $node->id, 'pelican_uuid' => 'uuid-1']);

        // Warm the health cache the way the deferred probe would.
        app(NodeHealthService::class)->checkNode($node);

        $response = $this->actingAs($admin)->getJson('/api/admin/servers');

        $response->assertOk()
            ->assertJsonPath('data.0.node.name', 'node-fr-1')
            ->assertJsonPath('data.0.node.health.status', 'healthy');
    }

    public function test_unprobed_node_returns_null_health_without_blocking(): void
    {
        Http::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $node = $this->makeNode();
        $this->makeServer($admin, ['node_id' => $node->id, 'pelican_uuid' => 'uuid-1']);

        $response = $this->actingAs($admin)->getJson('/api/admin/servers');

        $response->assertOk()
            ->assertJsonPath('data.0.node.name', 'node-fr-1')
            ->assertJsonPath('data.0.node.health', null);
    }

    public function test_missing_node_link_is_backfilled_from_pelican(): void
    {
        Http::fake([
            '*/api/application/servers/42*' => Http::response([
                'attributes' => [
                    'id' => 42,
                    'identifier' => 'abc12345',
                    'uuid' => 'abc12345-1111-2222-3333-444455556666',
                    'name' => 'Test server',
                    'user' => 1,
                    'node' => 5,
                    'egg' => 3,
                    'limits' => [],
                ],
            ], 200),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);
        $node = $this->makeNode();
        $server = $this->makeServer($admin);

        $response = $this->actingAs($admin)->getJson('/api/admin/servers');

        $response->assertOk()->assertJsonPath('data.0.node.name', 'node-fr-1');
        $this->assertSame($node->id, $server->fresh()->node_id);
    }

    public function test_admin_list_survives_poisoned_health_cache(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);
        $node = $this->makeNode();
        $this->makeServer($admin, ['node_id' => $node->id, 'pelican_uuid' => 'uuid-1']);

        // A stale entry from another code version unserializes to
        // __PHP_Incomplete_Class — the list must degrade, never 500.
        $legacyClass = 'App\Services\Wings\NodeHealthReport';
        Cache::put(
            "wings_health:node:{$node->id}",
            unserialize(sprintf('O:%d:"%s":0:{}', strlen($legacyClass), $legacyClass)),
            30,
        );

        $this->actingAs($admin)->getJson('/api/admin/servers')
            ->assertOk()
            ->assertJsonPath('data.0.node.name', 'node-fr-1')
            ->assertJsonPath('data.0.node.health', null);
    }

    public function test_non_admin_cannot_list(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->getJson('/api/admin/servers')->assertForbidden();
    }
}
