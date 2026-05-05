<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\DTO;

final readonly class ModpackProviderCapabilities
{
    public function __construct(
        public bool $search,
        public bool $pagination,
        public bool $minecraftVersionFilter,
        public bool $loaderFilter,
        public bool $serverMarker,
        public bool $multipleVersions,
    ) {}

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'pagination' => $this->pagination,
            'minecraft_version_filter' => $this->minecraftVersionFilter,
            'loader_filter' => $this->loaderFilter,
            'server_marker' => $this->serverMarker,
            'multiple_versions' => $this->multipleVersions,
        ];
    }
}
