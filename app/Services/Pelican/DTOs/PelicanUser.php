<?php

namespace App\Services\Pelican\DTOs;

final readonly class PelicanUser
{
    public function __construct(
        public int $id,
        public string $email,
        public string $username,
        public string $name,
        public bool $isAdmin,
        public string $createdAt,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? $data;

        // Pelican uses 'name', Pterodactyl used 'first_name'/'last_name'
        $name = $attributes['name']
            ?? trim(($attributes['first_name'] ?? '') . ' ' . ($attributes['last_name'] ?? ''))
            ?: $attributes['username'];

        return new self(
            id: $attributes['id'],
            email: $attributes['email'],
            username: $attributes['username'],
            name: $name,
            isAdmin: $attributes['root_admin'] ?? $attributes['admin'] ?? false,
            createdAt: $attributes['created_at'] ?? '',
        );
    }
}
