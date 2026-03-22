<?php

namespace App\Services\Pelican\DTOs;

final readonly class PelicanNode
{
    public function __construct(
        public int $id,
        public string $name,
        public string $fqdn,
        public int $memory,
        public int $disk,
        public string $location,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? $data;
        return new self(
            id: $attributes['id'],
            name: $attributes['name'],
            fqdn: $attributes['fqdn'],
            memory: $attributes['memory'] ?? 0,
            disk: $attributes['disk'] ?? 0,
            location: $attributes['location_id'] ?? '',
        );
    }
}
