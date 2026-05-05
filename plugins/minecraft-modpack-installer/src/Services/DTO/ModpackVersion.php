<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\DTO;

final readonly class ModpackVersion
{
    /**
     * @param  list<string>  $minecraftVersions
     * @param  list<string>  $loaders        loader names normalised lower-case
     */
    public function __construct(
        public string $versionId,
        public string $label,
        public array $minecraftVersions,
        public array $loaders,
        public string $releaseType,
    ) {}
}
