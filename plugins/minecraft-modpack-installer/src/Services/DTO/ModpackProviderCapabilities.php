<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\DTO;

/**
 * Declares what a given provider can do, so the unified search UI can adapt
 * its filter bar at runtime instead of hardcoding per-provider knobledge.
 *
 * `sortModes` are the *canonical* sort identifiers exposed in the UI
 * (`relevance | popular | updated | newest | name | downloads`); each provider
 * is responsible for translating them to its own backend field. Providers
 * that cannot sort at all return an empty list.
 */
final readonly class ModpackProviderCapabilities
{
    /** @param  list<string>  $sortModes */
    public function __construct(
        public bool $search,
        public bool $pagination,
        public bool $minecraftVersionFilter,
        public bool $loaderFilter,
        public bool $serverMarker,
        public bool $multipleVersions,
        public array $sortModes = [],
        public bool $categoryFilter = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'pagination' => $this->pagination,
            'minecraft_version_filter' => $this->minecraftVersionFilter,
            'loader_filter' => $this->loaderFilter,
            'server_marker' => $this->serverMarker,
            'multiple_versions' => $this->multipleVersions,
            'sort_modes' => $this->sortModes,
            'category_filter' => $this->categoryFilter,
        ];
    }
}
