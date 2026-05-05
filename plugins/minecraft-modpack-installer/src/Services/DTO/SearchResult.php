<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\DTO;

final readonly class SearchResult
{
    /**
     * @param  list<ModpackSummary>  $hits
     */
    public function __construct(
        public array $hits,
        public int $total,
        public int $currentPage,
        public int $perPage,
    ) {}

    public function lastPage(): int
    {
        if ($this->perPage <= 0) {
            return 1;
        }

        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
