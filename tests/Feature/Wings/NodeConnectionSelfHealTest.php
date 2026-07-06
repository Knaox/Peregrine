<?php

declare(strict_types=1);

namespace Tests\Feature\Wings;

use App\Enums\NodeHealthStatus;
use App\Models\Node;
use App\Services\Wings\NodeHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Self-heal of stale daemon connection details: installs that mirrored
 * Pelican's `daemon_listen` (bind port, 8080) instead of `daemon_connect`
 * probe the wrong port and report healthy nodes as unreachable. A
 * connection failure must re-pull the node from Pelican (cooldown-gated)
 * and retry against the corrected address.
 */
class NodeConnectionSelfHealTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Absolute Pelican base URL so the refresher's request matches the
        // Http::fake patterns (mirrors a configured install).
        config(['panel.pelican.url' => 'https://pelican.test']);
    }

    private function makeNode(): Node
    {
        return Node::create([
            'pelican_node_id' => 5,
            'name' => 'node-fr-1',
            'fqdn' => 'node1.test',
            'scheme' => 'http',
            'daemon_listen' => 8080, // stale bind port, real connect port differs
            'daemon_token' => 'valid-token',
            'memory' => 32768,
            'disk' => 512000,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pelicanNodePayload(int $connectPort): array
    {
        return ['attributes' => [
            'id' => 5,
            'name' => 'node-fr-1',
            'fqdn' => 'node1.test',
            'scheme' => 'http',
            'daemon_connect' => $connectPort,
            'daemon_listen' => 8080,
            'maintenance_mode' => false,
        ]];
    }

    public function test_stale_port_heals_from_pelican_and_probe_retries(): void
    {
        Http::fake([
            'http://node1.test:8080/api/system' => fn () => throw new ConnectionException('cURL error 7: could not connect'),
            '*/api/application/nodes/5' => Http::response($this->pelicanNodePayload(4546), 200),
            'http://node1.test:4546/api/system' => Http::response(['version' => '1.0.0'], 200),
        ]);

        $node = $this->makeNode();
        $report = app(NodeHealthService::class)->checkNode($node);

        $this->assertSame(NodeHealthStatus::Healthy, $report->status);
        $this->assertSame(4546, $node->fresh()->daemon_listen);
    }

    public function test_refresh_is_cooldown_gated(): void
    {
        Http::fake([
            'http://node1.test:8080/api/system' => fn () => throw new ConnectionException('cURL error 7'),
            '*/api/application/nodes/5' => Http::response($this->pelicanNodePayload(4546), 200),
            'http://node1.test:4546/api/system' => fn () => throw new ConnectionException('cURL error 7'),
        ]);

        $node = $this->makeNode();
        $service = app(NodeHealthService::class);

        $this->assertSame(NodeHealthStatus::Unreachable, $service->checkNode($node)->status);

        // Next probe round inside the cooldown: no second Pelican hit.
        Cache::forget("wings_health:node:{$node->id}");
        $this->assertSame(NodeHealthStatus::Unreachable, $service->checkNode($node->fresh())->status);

        $pelicanCalls = Http::recorded(
            fn ($request) => str_contains($request->url(), '/api/application/nodes/5'),
        )->count();
        $this->assertSame(1, $pelicanCalls);
    }

    public function test_unchanged_address_reports_unreachable_without_retry(): void
    {
        $probes = 0;
        Http::fake([
            'http://node1.test:8080/api/system' => function () use (&$probes) {
                $probes++;

                throw new ConnectionException('cURL error 7');
            },
            '*/api/application/nodes/5' => Http::response($this->pelicanNodePayload(8080), 200),
        ]);

        $node = $this->makeNode();
        $report = app(NodeHealthService::class)->checkNode($node);

        $this->assertSame(NodeHealthStatus::Unreachable, $report->status);
        // The re-fetched address is identical, so the probe must not retry.
        $this->assertSame(1, $probes);
    }
}
