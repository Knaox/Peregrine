<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\DTO;

use Plugins\MinecraftModpackInstaller\Enums\ModpackLoader;

final readonly class SearchCriteria
{
    public function __construct(
        public ?string $query,
        public ?string $minecraftVersion,
        public ?ModpackLoader $loader,
        public int $page,
        public int $pageSize,
    ) {}
}
