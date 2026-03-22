<?php

namespace App\Services\Pelican\DTOs;

final readonly class PelicanUser
{
    public function __construct(
        public int $id,
        public string $email,
        public string $username,
        public string $firstName,
        public string $lastName,
        public bool $isAdmin,
        public string $createdAt,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? $data;
        return new self(
            id: $attributes['id'],
            email: $attributes['email'],
            username: $attributes['username'],
            firstName: $attributes['first_name'],
            lastName: $attributes['last_name'],
            isAdmin: $attributes['root_admin'] ?? false,
            createdAt: $attributes['created_at'] ?? '',
        );
    }
}
