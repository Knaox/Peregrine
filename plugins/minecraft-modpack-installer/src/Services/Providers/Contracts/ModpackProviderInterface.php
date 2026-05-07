<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\Providers\Contracts;

use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackCategory;
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

    /**
     * Provider-side categories/tags surfaced in the unified filter bar.
     * Default empty list keeps providers without a category concept (Technic,
     * VoidsWrath) from having to override this method.
     *
     * @return list<ModpackCategory>
     */
    public function listCategories(): array;

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
     * Returns ALL versions of a modpack — implementations MUST paginate
     * through every page the provider exposes (CurseForge caps pageSize at
     * 50 but a popular pack can have several hundred files). The unified UI
     * relies on this to show a complete dropdown.
     *
     * @return list<ModpackVersion>
     */
    public function listVersions(string $modpackId, ?string $minecraftVersion): array;
}
