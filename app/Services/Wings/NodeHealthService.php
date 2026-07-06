<?php

namespace App\Services\Wings;

use App\Enums\NodeHealthStatus;
use App\Models\Node;
use App\Services\Wings\DTOs\NodeHealthReport;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

/**
 * Probes a Wings node (and optionally one server on it) and classifies the
 * outcome for both the admin panel and the player-facing API.
 *
 * Three-stage probe, cheapest first, aborting on the first failure:
 *   1. GET /api/system                 → node reachable? how fast? version?
 *   2. GET /api/servers/{uuid}         → is the server known to Wings?
 *   3. GET …/files/list-directory (/)  → do real server operations work?
 *      Stage 3 catches the degraded-Wings mode where /api/system and the
 *      websocket still answer while every file/server operation returns a
 *      generic HTTP 500 (observed on LXC/Proxmox hosts) — the exact class
 *      of failure Pelican shows as "Could not load files!".
 *
 * Results are cached 30s per node / per server: Wings is not behind the
 * Pelican panel throttle, but page loads must never stampede a daemon.
 */
class NodeHealthService
{
    private const CACHE_TTL_SECONDS = 30;

    public const SLOW_THRESHOLD_MS = 1500;

    public function __construct(
        private readonly WingsDaemonClient $wings,
        private readonly NodeDaemonCredentialsResolver $credentials,
    ) {}

    public function checkNode(Node $node): NodeHealthReport
    {
        return Cache::remember(
            "wings_health:node:{$node->id}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->probeNode($node),
        );
    }

    public function checkServerOnNode(Node $node, string $serverUuid): NodeHealthReport
    {
        return Cache::remember(
            "wings_health:server:{$serverUuid}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->probeServer($node, $serverUuid),
        );
    }

    private function probeNode(Node $node): NodeHealthReport
    {
        if ($node->maintenance_mode) {
            return NodeHealthReport::make(NodeHealthStatus::Maintenance);
        }

        if (! $this->credentials->ensure($node)) {
            return NodeHealthReport::make(
                NodeHealthStatus::Unknown,
                detail: 'Daemon credentials unavailable — check the Pelican API key has node permissions, then re-sync nodes.',
            );
        }

        try {
            $startedAt = microtime(true);
            $response = $this->wings->getSystem($node);
        } catch (ConnectionException $e) {
            return NodeHealthReport::make(NodeHealthStatus::Unreachable, detail: $e->getMessage());
        }

        // 401/403 → the daemon token rotated on the Pelican side. Re-hydrate
        // once and retry so a rotation heals itself without admin action.
        if (in_array($response->status(), [401, 403], true)) {
            if (! $this->credentials->refresh($node)) {
                return NodeHealthReport::make(
                    NodeHealthStatus::AuthFailed,
                    detail: 'Wings rejected the daemon token and it could not be refreshed from Pelican.',
                );
            }

            try {
                $startedAt = microtime(true);
                $response = $this->wings->getSystem($node);
            } catch (ConnectionException $e) {
                return NodeHealthReport::make(NodeHealthStatus::Unreachable, detail: $e->getMessage());
            }

            if (in_array($response->status(), [401, 403], true)) {
                return NodeHealthReport::make(
                    NodeHealthStatus::AuthFailed,
                    detail: 'Wings still rejects the daemon token after a refresh from Pelican.',
                );
            }
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response->failed()) {
            return NodeHealthReport::make(
                NodeHealthStatus::Unreachable,
                latencyMs: $latencyMs,
                detail: "Wings answered HTTP {$response->status()} on /api/system: ".$this->truncate($response->body()),
            );
        }

        return NodeHealthReport::make(
            $latencyMs >= self::SLOW_THRESHOLD_MS ? NodeHealthStatus::Degraded : NodeHealthStatus::Healthy,
            latencyMs: $latencyMs,
            wingsVersion: $this->stringOrNull($response->json('version')),
        );
    }

    private function probeServer(Node $node, string $serverUuid): NodeHealthReport
    {
        $nodeReport = $this->checkNode($node);
        if ($nodeReport->status !== NodeHealthStatus::Healthy && $nodeReport->status !== NodeHealthStatus::Degraded) {
            return $nodeReport;
        }

        try {
            $server = $this->wings->getServer($node, $serverUuid);
        } catch (ConnectionException $e) {
            return $this->serverReport($nodeReport, NodeHealthStatus::ServerUnreachable, $e->getMessage());
        }

        if ($server->status() === 404) {
            return $this->serverReport(
                $nodeReport,
                NodeHealthStatus::ServerUnreachable,
                'The server is unknown to Wings (missing, transferring, or not yet installed on this node).',
            );
        }

        if ($server->failed()) {
            return $this->serverReport(
                $nodeReport,
                NodeHealthStatus::ServerErrors,
                "Wings answered HTTP {$server->status()} for this server: ".$this->truncate($server->body()),
            );
        }

        try {
            $files = $this->wings->listServerRootFiles($node, $serverUuid);
        } catch (ConnectionException $e) {
            return $this->serverReport($nodeReport, NodeHealthStatus::ServerUnreachable, $e->getMessage());
        }

        if ($files->serverError()) {
            return $this->serverReport(
                $nodeReport,
                NodeHealthStatus::ServerErrors,
                'Server file operations fail on Wings: '.$this->truncate($files->body()),
            );
        }

        return $nodeReport;
    }

    private function serverReport(NodeHealthReport $nodeReport, NodeHealthStatus $status, string $detail): NodeHealthReport
    {
        return NodeHealthReport::make(
            $status,
            latencyMs: $nodeReport->latencyMs,
            wingsVersion: $nodeReport->wingsVersion,
            detail: $detail,
        );
    }

    private function truncate(string $body): string
    {
        return mb_substr($body, 0, 300);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
