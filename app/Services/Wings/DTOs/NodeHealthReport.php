<?php

namespace App\Services\Wings\DTOs;

use App\Enums\NodeHealthStatus;
use Carbon\CarbonImmutable;

/**
 * Result of a node / server-on-node health probe. Cached (30s) and shared
 * by the admin panel and the player API, so `detail` (raw technical info —
 * may contain Wings error bodies and request ids) is only serialized when
 * explicitly requested (admin surfaces).
 */
final readonly class NodeHealthReport
{
    public function __construct(
        public NodeHealthStatus $status,
        public ?int $latencyMs = null,
        public ?string $wingsVersion = null,
        public ?string $detail = null,
        public ?CarbonImmutable $checkedAt = null,
    ) {}

    public static function make(
        NodeHealthStatus $status,
        ?int $latencyMs = null,
        ?string $wingsVersion = null,
        ?string $detail = null,
    ): self {
        return new self($status, $latencyMs, $wingsVersion, $detail, CarbonImmutable::now());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(bool $withDetail = false): array
    {
        $payload = [
            'status' => $this->status->value,
            'severity' => $this->status->severity(),
            'latency_ms' => $this->latencyMs,
            'wings_version' => $this->wingsVersion,
            'checked_at' => $this->checkedAt?->toIso8601String(),
        ];

        if ($withDetail) {
            $payload['detail'] = $this->detail;
        }

        return $payload;
    }
}
