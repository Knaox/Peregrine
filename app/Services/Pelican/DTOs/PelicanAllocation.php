<?php

namespace App\Services\Pelican\DTOs;

/**
 * Network allocation (IP+port) on a Pelican node.
 *
 * Source : Application API `/api/application/nodes/{id}/allocations`. Used by
 * Bridge::PortAllocator to find free port blocks at provisioning time, and
 * by AllocationMirrorBackfiller to mirror only assigned allocations.
 *
 * `serverId` is populated when the request was made with `?include=server`.
 * Without that include the field stays null even on assigned allocations —
 * callers that need to filter by server attribution must request the include.
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
        public ?int $serverId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? $data;

        // Accept both shapes Pelican uses : a top-level `server_id` (rare,
        // newer builds) and the relationship object exposed when the caller
        // adds `?include=server` to the listing.
        $serverId = $attributes['server_id'] ?? null;
        if ($serverId === null) {
            $serverId = $attributes['relationships']['server']['attributes']['id']
                ?? $data['relationships']['server']['attributes']['id']
                ?? null;
        }

        return new self(
            id: (int) $attributes['id'],
            ip: (string) $attributes['ip'],
            ipAlias: $attributes['ip_alias'] ?? null,
            port: (int) $attributes['port'],
            notes: $attributes['notes'] ?? null,
            assigned: (bool) ($attributes['assigned'] ?? false),
            serverId: $serverId !== null ? (int) $serverId : null,
        );
    }
}
