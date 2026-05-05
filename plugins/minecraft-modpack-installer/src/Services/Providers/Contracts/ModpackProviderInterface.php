<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\Providers\Contracts;

use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackProviderCapabilities;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackVersion;
use Plugins\MinecraftModpackInstaller\Services\DTO\SearchCriteria;
use Plugins\MinecraftModpackInstaller\Services\DTO\SearchResult;

interface ModpackProviderInterface
{
    public function id(): ModpackProvider;

    public function isConfigured(): bool;

    public function capabilities(): ModpackProviderCapabilities;

    /** @return list<string> */
    public function listMinecraftVersions(): array;

    public function search(SearchCriteria $criteria): SearchResult;

    /**
     * @return list<ModpackVersion>
     */
    public function listVersions(string $modpackId, ?string $minecraftVersion): array;
}
