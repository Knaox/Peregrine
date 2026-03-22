<?php

namespace App\Services\Pelican\DTOs;

final readonly class ServerLimits
{
    public function __construct(
        public int $memory,
        public int $swap,
        public int $disk,
        public int $io,
        public int $cpu,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            memory: $data['memory'] ?? 0,
            swap: $data['swap'] ?? 0,
            disk: $data['disk'] ?? 0,
            io: $data['io'] ?? 500,
            cpu: $data['cpu'] ?? 0,
        );
    }
}
