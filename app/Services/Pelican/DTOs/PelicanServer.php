<?php

namespace App\Services\Pelican\DTOs;

final readonly class PelicanServer
{
    public function __construct(
        public int $id,
        public string $identifier,
        public string $name,
        public string $description,
        public int $userId,
        public int $nodeId,
        public int $eggId,
        public int $nestId,
        public bool $isSuspended,
        public ServerLimits $limits,
        public ?string $sftpHost = null,
        public ?string $sftpAlias = null,
        public int $sftpPort = 2022,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        $attributes = $data['attributes'] ?? $data;
        return new self(
            id: $attributes['id'],
            identifier: $attributes['identifier'] ?? $attributes['uuid'] ?? '',
            name: $attributes['name'],
            description: $attributes['description'] ?? '',
            userId: $attributes['user'],
            nodeId: $attributes['node'],
            eggId: $attributes['egg'],
            nestId: $attributes['nest'] ?? 0,
            isSuspended: ($attributes['status'] === 'suspended') || ($attributes['suspended'] ?? false),
            limits: ServerLimits::fromApiResponse($attributes['limits'] ?? []),
            sftpHost: $attributes['sftp_details']['ip'] ?? null,
            sftpAlias: $attributes['sftp_details']['alias'] ?? null,
            sftpPort: $attributes['sftp_details']['port'] ?? 2022,
        );
    }
}
