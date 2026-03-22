<?php

namespace App\Services\DTOs;

final readonly class SyncComparison
{
    /**
     * @param array<int, mixed> $new       Items that exist in Pelican but not locally.
     * @param array<int, mixed> $synced    Items that exist in both Pelican and locally.
     * @param array<int, mixed> $orphaned  Items that exist locally but not in Pelican.
     */
    public function __construct(
        public array $new,
        public array $synced,
        public array $orphaned,
    ) {}
}
