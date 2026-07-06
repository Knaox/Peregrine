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

class NodeHealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private const WINGS = 'http://node1.test:8080';

    private function makeNode(array $overrides = []): Node
    {
        return Node::create(array_merge([
            'pelican_node_id' => 5,
            'name' => 'node-fr-1',
            'fqdn' => 'node1.test',
            'scheme' => 'http',
            'daemon_listen' => 8080,
            'daemon_token' => 'valid-token',
            'memory' => 32768,
            'disk' => 512000,
        ], $overrides));
    }

    private function service(): NodeHealthService
    {
        return app(NodeHealthService::class);
    }

    public function test_reachable_wings_reports_healthy_with_version(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0-beta26'], 200),
        ]);

        $report = $this->service()->checkNode($this->makeNode());

        $this->assertSame(NodeHealthStatus::Healthy, $report->status);
        $this->assertSame('1.0.0-beta26', $report->wingsVersion);
        $this->assertNotNull($report->latencyMs);
    }

    public function test_connection_failure_reports_unreachable(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => fn () => throw new ConnectionException('cURL error 28: timeout'),
        ]);

        $report = $this->service()->checkNode($this->makeNode());

        $this->assertSame(NodeHealthStatus::Unreachable, $report->status);
        $this->assertStringContainsString('cURL error 28', (string) $report->detail);
    }

    public function test_missing_token_is_hydrated_from_pelican_configuration(): void
    {
        Http::fake([
            '*/api/application/nodes/5/configuration' => Http::response([
                'token_id' => 'tid123',
                'token' => 'hydrated-token',
            ], 200),
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
        ]);

        $node = $this->makeNode(['daemon_token' => null]);
        $report = $this->service()->checkNode($node);

        $this->assertSame(NodeHealthStatus::Healthy, $report->status);
        $this->assertSame('hydrated-token', $node->fresh()->daemon_token);
        $this->assertSame('tid123', $node->fresh()->daemon_token_id);
    }

    public function test_missing_token_and_failing_configuration_reports_unknown(): void
    {
        Http::fake([
            '*/api/application/nodes/5/configuration' => Http::response(['error' => 'nope'], 403),
        ]);

        $report = $this->service()->checkNode($this->makeNode(['daemon_token' => null]));

        $this->assertSame(NodeHealthStatus::Unknown, $report->status);
    }

    public function test_rotated_token_self_heals_via_pelican_refresh(): void
    {
        Http::fake([
            '*/api/application/nodes/5/configuration' => Http::response([
                'token_id' => 'tid-new',
                'token' => 'rotated-token',
            ], 200),
            self::WINGS.'/api/system' => Http::sequence()
                ->push(['error' => 'You are not authorized to access this endpoint.'], 403)
                ->push(['version' => '1.0.0'], 200),
        ]);

        $node = $this->makeNode(['daemon_token' => 'stale-token']);
        $report = $this->service()->checkNode($node);

        $this->assertSame(NodeHealthStatus::Healthy, $report->status);
        $this->assertSame('rotated-token', $node->fresh()->daemon_token);
    }

    public function test_persistent_401_reports_auth_failed(): void
    {
        Http::fake([
            '*/api/application/nodes/5/configuration' => Http::response([
                'token_id' => 'tid',
                'token' => 'still-rejected',
            ], 200),
            self::WINGS.'/api/system' => Http::response(['error' => 'denied'], 401),
        ]);

        $report = $this->service()->checkNode($this->makeNode());

        $this->assertSame(NodeHealthStatus::AuthFailed, $report->status);
    }

    public function test_maintenance_mode_short_circuits_without_probing(): void
    {
        Http::fake();

        $report = $this->service()->checkNode($this->makeNode(['maintenance_mode' => true]));

        $this->assertSame(NodeHealthStatus::Maintenance, $report->status);
        Http::assertNothingSent();
    }

    public function test_server_unknown_to_wings_reports_server_unreachable(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
            self::WINGS.'/api/servers/uuid-1*' => Http::response(['error' => 'not found'], 404),
        ]);

        $report = $this->service()->checkServerOnNode($this->makeNode(), 'uuid-1');

        $this->assertSame(NodeHealthStatus::ServerUnreachable, $report->status);
    }

    public function test_file_operations_500_reports_server_errors(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
            self::WINGS.'/api/servers/uuid-1/files/list-directory*' => Http::response([
                'error' => 'An unexpected error was encountered while processing this request',
                'request_id' => '4a9bd662-611d-4778-8cff-d6745',
            ], 500),
            self::WINGS.'/api/servers/uuid-1' => Http::response(['state' => 'running'], 200),
        ]);

        $report = $this->service()->checkServerOnNode($this->makeNode(), 'uuid-1');

        $this->assertSame(NodeHealthStatus::ServerErrors, $report->status);
        $this->assertStringContainsString('unexpected error', (string) $report->detail);
    }

    public function test_fully_working_server_inherits_healthy_node_status(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
            self::WINGS.'/api/servers/uuid-1/files/list-directory*' => Http::response(['data' => []], 200),
            self::WINGS.'/api/servers/uuid-1' => Http::response(['state' => 'running'], 200),
        ]);

        $report = $this->service()->checkServerOnNode($this->makeNode(), 'uuid-1');

        $this->assertSame(NodeHealthStatus::Healthy, $report->status);
    }

    public function test_unreachable_node_short_circuits_server_probe(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => fn () => throw new ConnectionException('refused'),
        ]);

        $report = $this->service()->checkServerOnNode($this->makeNode(), 'uuid-1');

        $this->assertSame(NodeHealthStatus::Unreachable, $report->status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/servers/'));
    }

    public function test_node_report_is_cached_between_calls(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
        ]);

        $node = $this->makeNode();
        $service = $this->service();
        $service->checkNode($node);
        $service->checkNode($node);

        Http::assertSentCount(1);
    }

    /**
     * Simulates the 30s report cache expiring between two SPA polls, so the
     * next checkNode() runs a fresh probe round.
     */
    private function expireReportCaches(Node $node, ?string $serverUuid = null): void
    {
        Cache::forget("wings_health:node:{$node->id}");
        if ($serverUuid !== null) {
            Cache::forget("wings_health:server:{$serverUuid}");
        }
    }

    public function test_single_probe_blip_after_a_healthy_round_keeps_reporting_healthy(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::sequence()
                ->push(['version' => '1.0.0'], 200)
                ->pushFailedConnection('cURL error 28: timeout'),
        ]);

        $node = $this->makeNode();
        $service = $this->service();

        $this->assertSame(NodeHealthStatus::Healthy, $service->checkNode($node)->status);

        $this->expireReportCaches($node);

        // One transient blip must NOT flip the player banner — the last
        // healthy report is served until a second round confirms the outage.
        $this->assertSame(NodeHealthStatus::Healthy, $service->checkNode($node)->status);
    }

    public function test_two_consecutive_failed_rounds_report_unreachable(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::sequence()
                ->push(['version' => '1.0.0'], 200)
                ->pushFailedConnection('refused')
                ->pushFailedConnection('refused'),
        ]);

        $node = $this->makeNode();
        $service = $this->service();

        $this->assertSame(NodeHealthStatus::Healthy, $service->checkNode($node)->status);

        $this->expireReportCaches($node);
        $this->assertSame(NodeHealthStatus::Healthy, $service->checkNode($node)->status);

        $this->expireReportCaches($node);
        $report = $service->checkNode($node);

        $this->assertSame(NodeHealthStatus::Unreachable, $report->status);
        $this->assertStringContainsString('refused', (string) $report->detail);
    }

    public function test_recovery_resets_the_failure_streak(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::sequence()
                ->push(['version' => '1.0.0'], 200)
                ->pushFailedConnection('blip 1')
                ->push(['version' => '1.0.0'], 200)
                ->pushFailedConnection('blip 2'),
        ]);

        $node = $this->makeNode();
        $service = $this->service();

        $this->assertSame(NodeHealthStatus::Healthy, $service->checkNode($node)->status);
        $this->expireReportCaches($node);
        $this->assertSame(NodeHealthStatus::Healthy, $service->checkNode($node)->status); // blip 1 masked
        $this->expireReportCaches($node);
        $this->assertSame(NodeHealthStatus::Healthy, $service->checkNode($node)->status); // genuine recovery
        $this->expireReportCaches($node);

        // blip 2 starts a NEW streak (the healthy round reset the counter).
        $this->assertSame(NodeHealthStatus::Healthy, $service->checkNode($node)->status);
    }

    public function test_single_server_probe_blip_keeps_reporting_healthy(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => Http::response(['version' => '1.0.0'], 200),
            self::WINGS.'/api/servers/uuid-1/files/list-directory*' => Http::sequence()
                ->push(['data' => []], 200)
                ->pushFailedConnection('cURL error 28: timeout'),
            self::WINGS.'/api/servers/uuid-1' => Http::response(['state' => 'running'], 200),
        ]);

        $node = $this->makeNode();
        $service = $this->service();

        $this->assertSame(NodeHealthStatus::Healthy, $service->checkServerOnNode($node, 'uuid-1')->status);

        $this->expireReportCaches($node, 'uuid-1');

        $this->assertSame(NodeHealthStatus::Healthy, $service->checkServerOnNode($node, 'uuid-1')->status);
    }

    public function test_never_seen_healthy_node_reports_its_failure_immediately(): void
    {
        Http::fake([
            self::WINGS.'/api/system' => fn () => throw new ConnectionException('refused'),
        ]);

        // No healthy history at all (cold cache) → a genuinely down node
        // must show up on the very first probe, not after CONFIRM_ROUNDS.
        $report = $this->service()->checkNode($this->makeNode());

        $this->assertSame(NodeHealthStatus::Unreachable, $report->status);
    }
}
