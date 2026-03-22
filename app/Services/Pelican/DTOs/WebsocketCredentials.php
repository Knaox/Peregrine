<?php

namespace App\Services\Pelican\DTOs;

final readonly class WebsocketCredentials
{
    public function __construct(
        public string $token,
        public string $socket,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['data'] ?? $data;
        return new self(
            token: $attributes['token'],
            socket: $attributes['socket'],
        );
    }
}
