<?php

namespace App\Services\Pelican\DTOs;

/**
 * Network allocation (IP+port) on a Pelican node.
 *
 * Source : Application API `/api/application/nodes/{id}/allocations`. Used by
 * Bridge::PortAllocator to find free port blocks at provisioning time.
 */
final readonly class PelicanAllocation
{
    public function __construct(
        public int $id,
        public string $ip,
        public ?string $ipAlias,
        public int $port,
        public ?string $notes,
        public bool $assigned,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? $data;

        return new self(
            id: (int) $attributes['id'],
            ip: (string) $attributes['ip'],
            ipAlias: $attributes['ip_alias'] ?? null,
            port: (int) $attributes['port'],
            notes: $attributes['notes'] ?? null,
            assigned: (bool) ($attributes['assigned'] ?? false),
        );
    }
}
