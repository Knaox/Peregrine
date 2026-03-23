<?php

namespace App\Services\Pelican\DTOs;

final readonly class ServerResources
{
    public function __construct(
        public float $cpuAbsolute,
        public int $memoryBytes,
        public int $diskBytes,
        public int $networkRxBytes,
        public int $networkTxBytes,
        public string $state,
        public int $uptime = 0,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? $data;
        $resources = $attributes['resources'] ?? $attributes;
        return new self(
            cpuAbsolute: $resources['cpu_absolute'] ?? 0.0,
            memoryBytes: $resources['memory_bytes'] ?? 0,
            diskBytes: $resources['disk_bytes'] ?? 0,
            networkRxBytes: $resources['network_rx_bytes'] ?? 0,
            networkTxBytes: $resources['network_tx_bytes'] ?? 0,
            state: $attributes['current_state'] ?? 'offline',
            uptime: $resources['uptime'] ?? 0,
        );
    }
}
