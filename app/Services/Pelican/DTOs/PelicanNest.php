<?php

namespace App\Services\Pelican\DTOs;

final readonly class PelicanNest
{
    public function __construct(
        public int $id,
        public string $name,
        public string $description,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? $data;
        return new self(
            id: $attributes['id'],
            name: $attributes['name'],
            description: $attributes['description'] ?? '',
        );
    }
}
