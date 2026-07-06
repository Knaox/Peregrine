<?php

declare(strict_types=1);

namespace Tests\Feature\Pelican;

use App\Actions\Pelican\ResolveServerNodeAction;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResolveServerNodeActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeServer(array $overrides = []): Server
    {
        return Server::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'pelican_server_id' => 42,
            'identifier' => 'abc12345',
            'name' => 'Test server',
            'status' => 'active',
        ], $overrides));
    }

    private function makeNode(int $pelicanNodeId = 5): Node
    {
        return Node::create([
            'pelican_node_id' => $pelicanNodeId,
            'name' => 'node-fr-1',
            'fqdn' => 'node1.test',
            'memory' => 32768,
            'disk' => 512000,
        ]);
    }

    private function pelicanServerPayload(int $nodeId = 5): array
    {
        return [
            'attributes' => [
                'id' => 42,
                'identifier' => 'abc12345',
                'uuid' => 'abc12345-1111-2222-3333-444455556666',
                'name' => 'Test server',
                'user' => 1,
                'node' => $nodeId,
                'egg' => 3,
                'limits' => [],
            ],
        ];
    }

    public function test_already_linked_server_returns_node_without_api_call(): void
    {
        Http::fake();
        $node = $this->makeNode();
        $server = $this->makeServer([
            'node_id' => $node->id,
            'pelican_uuid' => 'abc12345-1111-2222-3333-444455556666',
        ]);

        $resolved = app(ResolveServerNodeAction::class)($server);

        $this->assertSame($node->id, $resolved?->id);
        Http::assertNothingSent();
    }

    public function test_resolves_and_persists_node_and_uuid_from_pelican(): void
    {
        Http::fake([
            '*/api/application/servers/42*' => Http::response($this->pelicanServerPayload(), 200),
        ]);
        $node = $this->makeNode();
        $server = $this->makeServer();

        $resolved = app(ResolveServerNodeAction::class)($server);

        $this->assertSame($node->id, $resolved?->id);
        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'node_id' => $node->id,
            'pelican_uuid' => 'abc12345-1111-2222-3333-444455556666',
        ]);
    }

    public function test_mirrors_unknown_node_on_the_fly(): void
    {
        Http::fake([
            '*/api/application/servers/42*' => Http::response($this->pelicanServerPayload(9), 200),
            '*/api/application/nodes/9*' => Http::response([
                'attributes' => [
                    'id' => 9,
                    'name' => 'node-new',
                    'fqdn' => 'node9.test',
                    'scheme' => 'http',
                    'daemon_listen' => 8443,
                    'memory' => 1024,
                    'disk' => 10240,
                ],
            ], 200),
        ]);
        $server = $this->makeServer();

        $resolved = app(ResolveServerNodeAction::class)($server);

        $this->assertNotNull($resolved);
        $this->assertDatabaseHas('nodes', [
            'pelican_node_id' => 9,
            'name' => 'node-new',
            'daemon_listen' => 8443,
        ]);
        $this->assertSame($resolved->id, $server->fresh()->node_id);
    }

    public function test_returns_null_when_pelican_unreachable(): void
    {
        Http::fake([
            '*/api/application/servers/42*' => Http::response(['error' => 'down'], 500),
        ]);
        $server = $this->makeServer();

        $resolved = app(ResolveServerNodeAction::class)($server);

        $this->assertNull($resolved);
        $this->assertNull($server->fresh()->node_id);
    }

    public function test_returns_null_without_pelican_server_id(): void
    {
        Http::fake();
        $server = $this->makeServer(['pelican_server_id' => null]);

        $this->assertNull(app(ResolveServerNodeAction::class)($server));
        Http::assertNothingSent();
    }
}
