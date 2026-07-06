<?php

declare(strict_types=1);

namespace Tests\Feature\Wings;

use App\Enums\NodeHealthStatus;
use App\Models\Node;
use App\Services\Wings\NodeHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A wings_health:* cache entry serialized by another code version (rolling
 * deploy, stale container sharing the same Redis) unserializes to
 * __PHP_Incomplete_Class. These tests pin that such an entry is treated as
 * a cache miss — re-probed and overwritten — instead of TypeError-ing on
 * every read for the entry's whole TTL (which used to 500 the admin server
 * list through peekNode()).
 */
class NodeHealthCachePoisonTest extends TestCase
{
    use RefreshDatabase;

    private const WINGS = 'http://node1.test:8080';

    /** What unserialize() yields for an entry whose class no longer exists. */
    private function poison(): object
    {
        $legacyClass = 'App\Services\Wings\NodeHealthReport';
        $poison = unserialize(sprintf('O:%d:"%s":0:{}', strlen($legacyClass), $legacyClass));
        assert(is_object($poison));

        return $poison;
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
        ]);
    }

    public function test_peek_node_returns_null_on_poisoned_entry(): void
    {
        $node = $this->makeNode();
        Cache::put("wings_health:node:{$node->id}", $this->poison(), 30);

        $this->assertNull(app(NodeHealthService::class)->peekNode($node));
    }

    public function test_check_node_reprobes_and_overwrites_a_poisoned_entry(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
        ]);

        $node = $this->makeNode();
        Cache::put("wings_health:node:{$node->id}", $this->poison(), 30);

        $service = app(NodeHealthService::class);

        $this->assertSame(NodeHealthStatus::Healthy, $service->checkNode($node)->status);
        $this->assertSame(NodeHealthStatus::Healthy, $service->peekNode($node)?->status);
    }

    public function test_check_server_on_node_reprobes_a_poisoned_entry(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
            self::WINGS.'/api/servers/uuid-1' => Http::response(['state' => 'running'], 200),
            self::WINGS.'/api/servers/uuid-1/files/list-directory*' => Http::response([], 200),
        ]);

        $node = $this->makeNode();
        Cache::put('wings_health:server:uuid-1', $this->poison(), 30);

        $report = app(NodeHealthService::class)->checkServerOnNode($node, 'uuid-1');

        $this->assertSame(NodeHealthStatus::Healthy, $report->status);
    }
}
