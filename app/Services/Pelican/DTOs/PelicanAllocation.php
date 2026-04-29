<?php

namespace App\Services\Pelican\DTOs;

/**
 * Network allocation (IP+port) on a Pelican node.
 *
 * Source : Application API `/api/application/nodes/{id}/allocations`. Used by
 * Bridge::PortAllocator to find free port blocks at provisioning time.
 *
 * `serverId` is populated when the request was made with `?include=server`.
 * Without that include the field stays null even on assigned allocations.
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

        // Pelican exposes the alias under different keys depending on which
        // API surface answered : Application API → `alias`, Client API →
        // `ip_alias`. Read both so the mirror stores the same value the live
        // API path returned, regardless of which endpoint fed the row.
        $alias = $attributes['ip_alias'] ?? $attributes['alias'] ?? null;

        return new self(
            id: (int) $attributes['id'],
            ip: (string) $attributes['ip'],
            ipAlias: $alias,
            port: (int) $attributes['port'],
            notes: $attributes['notes'] ?? null,
            assigned: (bool) ($attributes['assigned'] ?? false),
            serverId: $serverId !== null ? (int) $serverId : null,
        );
    }
}
