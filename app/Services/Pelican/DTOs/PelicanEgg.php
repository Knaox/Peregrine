<?php

namespace App\Services\Pelican\DTOs;

final readonly class PelicanEgg
{
    public function __construct(
        public int $id,
        public int $nestId,
        public string $name,
        public string $dockerImage,
        public string $startup,
        public string $description,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? $data;
        return new self(
            id: $attributes['id'],
            nestId: $attributes['nest'] ?? 0,
            name: $attributes['name'],
            dockerImage: $attributes['docker_image'] ?? '',
            startup: $attributes['startup'] ?? '',
            description: $attributes['description'] ?? '',
        );
    }
}
