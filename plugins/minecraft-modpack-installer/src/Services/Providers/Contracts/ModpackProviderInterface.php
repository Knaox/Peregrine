<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\Providers\Contracts;

use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackProviderCapabilities;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackSummary;
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
     * Direct metadata lookup by modpack id/slug — used after the user picks
     * a modpack so the installation row stores the real name/icon/url
     * instead of guessing from a search hit. Returns null if the provider
     * cannot resolve the id (e.g. deleted pack), letting the orchestrator
     * fall back gracefully without writing wrong data.
     */
    public function getModpack(string $modpackId): ?ModpackSummary;

    /**
     * @return list<ModpackVersion>
     */
    public function listVersions(string $modpackId, ?string $minecraftVersion): array;
}
