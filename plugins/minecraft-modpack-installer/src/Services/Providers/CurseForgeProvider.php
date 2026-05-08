<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\Providers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Plugins\MinecraftModpackInstaller\Enums\ModpackLoader;
use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Exceptions\ProviderRequestException;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackCategory;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackProviderCapabilities;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackSummary;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackVersion;
use Plugins\MinecraftModpackInstaller\Services\DTO\SearchCriteria;
use Plugins\MinecraftModpackInstaller\Services\DTO\SearchResult;
use Plugins\MinecraftModpackInstaller\Services\ModpackSettingsService;
use Plugins\MinecraftModpackInstaller\Services\Providers\Contracts\ModpackProviderInterface;
use Throwable;

final class CurseForgeProvider implements ModpackProviderInterface
{
    private const BASE_URL = 'https://api.curseforge.com/v1';

    private const GAME_ID = 432;

    private const CLASS_ID_MODPACK = 4471;

    /** Maximum index+pageSize value the CurseForge API will accept. */
    private const RESULT_CAP = 10_000;

    /** Defensive cap on how many file pages we walk for a single modpack. */
    private const VERSION_PAGE_LIMIT = 20;

    /** Loader name → CurseForge numeric id. */
    private const LOADER_MAP = [
        ModpackLoader::Forge->value => 1,
        ModpackLoader::Fabric->value => 4,
        ModpackLoader::Quilt->value => 5,
        ModpackLoader::NeoForge->value => 6,
    ];

    /** Canonical sort id → CurseForge `sortField` integer. */
    private const SORT_MAP = [
        'relevance' => 1,  // Featured (CF's relevance/featured ranking)
        'popular' => 2,    // Popularity
        'updated' => 3,    // LastUpdated
        'name' => 4,       // Name
        'downloads' => 6,  // TotalDownloads
        'newest' => 11,    // ReleaseDate
    ];

    public function __construct(
        private readonly Factory $http,
        private readonly Repository $cache,
        private readonly string $userAgent,
        private readonly ModpackSettingsService $settings,
    ) {}

    public function id(): ModpackProvider
    {
        return ModpackProvider::CurseForge;
    }

    public function isConfigured(): bool
    {
        return $this->settings->curseforgeApiKey() !== null;
    }

    public function capabilities(): ModpackProviderCapabilities
    {
        return new ModpackProviderCapabilities(
            search: true,
            pagination: true,
            minecraftVersionFilter: true,
            loaderFilter: true,
            serverMarker: true,
            multipleVersions: true,
            sortModes: ['relevance', 'popular', 'updated', 'newest', 'name', 'downloads'],
            categoryFilter: true,
        );
    }

    /** @return list<string> */
    public function listMinecraftVersions(): array
    {
        return $this->cache->remember(
            'modpacks:curseforge:mc-versions',
            6 * 3600,
            fn (): array => $this->fetchMinecraftVersions(),
        );
    }

    /** @return list<ModpackCategory> */
    public function listCategories(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        return $this->cache->remember(
            'modpacks:curseforge:categories',
            24 * 3600,
            function (): array {
                try {
                    $response = $this->client()
                        ->get(self::BASE_URL.'/categories', [
                            'gameId' => self::GAME_ID,
                            'classId' => self::CLASS_ID_MODPACK,
                        ])
                        ->throw()
                        ->json();
                } catch (Throwable) {
                    return [];
                }

                $out = [];
                foreach ($response['data'] ?? [] as $entry) {
                    $id = $entry['id'] ?? null;
                    $name = $entry['name'] ?? null;
                    if (! is_int($id) && ! ctype_digit((string) $id)) {
                        continue;
                    }
                    if (! is_string($name) || $name === '') {
                        continue;
                    }
                    $out[] = new ModpackCategory(
                        id: (string) $id,
                        label: $name,
                        iconUrl: $entry['iconUrl'] ?? null,
                    );
                }

                usort($out, static fn ($a, $b) => strcasecmp($a->label, $b->label));

                return $out;
            },
        );
    }

    public function search(SearchCriteria $criteria): SearchResult
    {
        $index = max(0, ($criteria->page - 1) * $criteria->pageSize);
        if ($index + $criteria->pageSize > self::RESULT_CAP) {
            return new SearchResult(hits: [], total: 0, currentPage: $criteria->page, perPage: $criteria->pageSize);
        }

        $sortField = self::SORT_MAP[$criteria->sort ?? 'relevance'] ?? self::SORT_MAP['relevance'];

        $params = [
            'gameId' => self::GAME_ID,
            'classId' => self::CLASS_ID_MODPACK,
            'index' => $index,
            'pageSize' => min($criteria->pageSize, 50),
            'sortField' => $sortField,
            // CF treats name asc as "alphabetical"; for everything else
            // descending matches "most/biggest first" intuition.
            'sortOrder' => $sortField === self::SORT_MAP['name'] ? 'asc' : 'desc',
        ];
        if ($criteria->query !== null && $criteria->query !== '') {
            $params['searchFilter'] = $criteria->query;
        }
        if ($criteria->minecraftVersion !== null) {
            $params['gameVersion'] = $criteria->minecraftVersion;
        }
        if ($criteria->category !== null && $criteria->category !== '' && ctype_digit($criteria->category)) {
            $params['categoryId'] = (int) $criteria->category;
        }
        // CurseForge silently DROPS modLoaderType when gameVersion is missing
        // (documented quirk). Either pair them, or omit the loader to avoid
        // misleading "wrong version" results — we choose the latter so the
        // UI's loader dropdown still narrows results once a version is set.
        if ($criteria->loader !== null
            && isset(self::LOADER_MAP[$criteria->loader->value])
            && $criteria->minecraftVersion !== null) {
            $params['modLoaderType'] = self::LOADER_MAP[$criteria->loader->value];
        }

        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/mods/search', $params)
                ->throw()
                ->json();
        } catch (Throwable $e) {
            throw new ProviderRequestException($this->id(), 'search failed: '.$e->getMessage(), $e);
        }

        $hits = [];
        foreach ($response['data'] ?? [] as $entry) {
            // Defensive: when an MC version filter is set we must drop hits
            // whose published files don't actually include that version.
            // CF's `gameVersion` parameter scopes to MODS WITH FILES on that
            // version, not "modpacks targeting that version" — older 1.8.9
            // packs still appear when the user filters by 1.20.1 because
            // they have a stray 1.20-tagged metadata file. Verifying the
            // gameVersions array on `latestFilesIndexes` keeps the listing
            // honest.
            if ($criteria->minecraftVersion !== null
                && ! $this->modSupportsVersion($entry, $criteria->minecraftVersion)) {
                continue;
            }

            $hits[] = new ModpackSummary(
                provider: $this->id(),
                modpackId: (string) $entry['id'],
                name: (string) ($entry['name'] ?? ''),
                slug: $entry['slug'] ?? null,
                description: $entry['summary'] ?? null,
                iconUrl: $entry['logo']['thumbnailUrl']
                    ?? $entry['logo']['url']
                    ?? null,
                externalUrl: $entry['links']['websiteUrl'] ?? null,
                isServerCompatible: $this->detectServerCompatibility($entry),
            );
        }

        $total = (int) ($response['pagination']['totalCount'] ?? count($hits));
        $total = min($total, self::RESULT_CAP);

        return new SearchResult($hits, $total, $criteria->page, $criteria->pageSize);
    }

    public function getModpack(string $modpackId): ?ModpackSummary
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // Cache hits short-circuit. Misses are NEVER cached — `null` here
        // means the lookup failed (transient HTTP error, rate limit, key
        // briefly invalid…) and persisting that for 24h would poison
        // every subsequent install of the same modpack with the raw
        // numeric id as its display name (cf. the RLCraft 285109 →
        // "285109" header that prompted this fix). `cache->remember`
        // does not distinguish, so we cache misses by hand on success.
        $key = 'modpacks:curseforge:meta:'.sha1($modpackId);
        $cached = $this->cache->get($key);
        if ($cached instanceof ModpackSummary) {
            return $cached;
        }

        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/mods/'.rawurlencode($modpackId))
                ->throw()
                ->json();
        } catch (Throwable) {
            return null;
        }

        $entry = $response['data'] ?? null;
        if (! is_array($entry) || empty($entry['id'])) {
            return null;
        }

        $summary = new ModpackSummary(
            provider: $this->id(),
            modpackId: (string) $entry['id'],
            name: (string) ($entry['name'] ?? $modpackId),
            slug: $entry['slug'] ?? null,
            description: $entry['summary'] ?? null,
            iconUrl: $entry['logo']['thumbnailUrl']
                ?? $entry['logo']['url']
                ?? null,
            externalUrl: $entry['links']['websiteUrl'] ?? null,
            isServerCompatible: $this->detectServerCompatibility($entry),
        );

        $this->cache->put($key, $summary, 24 * 3600);

        return $summary;
    }

    /**
     * Direct fetch of one file's metadata via CurseForge's
     * `/v1/mods/{modId}/files/{fileId}` endpoint. One HTTP round-trip,
     * unlike `listVersions()` which paginates through every release of
     * the pack — RLCraft has 80+ files which used to push the orchestrator
     * past `max_execution_time` and 500 the install POST.
     *
     * Cache successes for 30 min (versions don't change after release;
     * shorter than `getModpack` because release notes / changelog can
     * be edited and we'd rather pick those up sooner). Misses are
     * never cached — same rationale as `getModpack`.
     */
    public function getVersion(string $modpackId, string $versionId): ?ModpackVersion
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $key = 'modpacks:curseforge:version:'.sha1($modpackId.'|'.$versionId);
        $cached = $this->cache->get($key);
        if ($cached instanceof ModpackVersion) {
            return $cached;
        }

        try {
            $response = $this->client()
                ->get(self::BASE_URL."/mods/{$modpackId}/files/{$versionId}")
                ->throw()
                ->json();
        } catch (Throwable) {
            return null;
        }

        $entry = $response['data'] ?? null;
        if (! is_array($entry) || empty($entry['id'])) {
            return null;
        }

        $version = $this->mapFileEntryToVersion($entry);
        if ($version === null) {
            return null;
        }

        $this->cache->put($key, $version, 30 * 60);

        return $version;
    }

    /**
     * Walks every page of the modpack's files endpoint until the API stops
     * returning new rows. Big modded packs (Better MC, ATM10, …) have more
     * than 50 files which the previous single-page implementation missed.
     *
     * @return list<ModpackVersion>
     */
    public function listVersions(string $modpackId, ?string $minecraftVersion): array
    {
        $perPage = 50; // CF's documented max
        $versions = [];
        $seen = [];

        for ($page = 0; $page < self::VERSION_PAGE_LIMIT; $page++) {
            $index = $page * $perPage;
            if ($index + $perPage > self::RESULT_CAP) {
                break;
            }

            $params = ['pageSize' => $perPage, 'index' => $index];
            if ($minecraftVersion !== null) {
                $params['gameVersion'] = $minecraftVersion;
            }

            try {
                $response = $this->client()
                    ->get(self::BASE_URL."/mods/{$modpackId}/files", $params)
                    ->throw()
                    ->json();
            } catch (Throwable $e) {
                throw new ProviderRequestException($this->id(), 'listVersions failed: '.$e->getMessage(), $e);
            }

            $batch = $response['data'] ?? [];
            if (! is_array($batch) || $batch === []) {
                break;
            }

            foreach ($batch as $entry) {
                $fileId = (string) ($entry['id'] ?? '');
                if ($fileId === '' || isset($seen[$fileId])) {
                    continue;
                }
                $seen[$fileId] = true;

                $version = $this->mapFileEntryToVersion($entry);
                if ($version !== null) {
                    $versions[] = $version;
                }
            }

            $total = (int) ($response['pagination']['totalCount'] ?? 0);
            if ($total > 0 && count($versions) >= $total) {
                break;
            }
            if (count($batch) < $perPage) {
                break;
            }
        }

        return $versions;
    }

    /** @return list<string> */
    private function fetchMinecraftVersions(): array
    {
        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/games/'.self::GAME_ID.'/versions')
                ->throw()
                ->json();
        } catch (Throwable) {
            return [];
        }

        $versions = [];
        foreach ($response['data'] ?? [] as $entry) {
            foreach ($entry['versions'] ?? [] as $v) {
                if (preg_match('/^\d+\.\d+(\.\d+)?$/', (string) $v) === 1) {
                    $versions[] = (string) $v;
                }
            }
        }
        $versions = array_values(array_unique($versions));
        usort($versions, static fn ($a, $b) => version_compare($b, $a));

        return $versions;
    }

    /** @param  array<string, mixed>  $entry */
    private function detectServerCompatibility(array $entry): ?bool
    {
        foreach ($entry['latestFiles'] ?? [] as $file) {
            if (! empty($file['isServerPack']) || ! empty($file['serverPackFileId'])) {
                return true;
            }
        }

        return null;
    }

    /**
     * Verifies a CurseForge mod entry actually publishes files for the
     * requested MC version. Looks at both `latestFilesIndexes` (the
     * authoritative per-version index) and the fallback `latestFiles[].gameVersions`
     * because CF returns one shape or the other depending on the endpoint
     * version.
     *
     * @param  array<string, mixed>  $entry
     */
    private function modSupportsVersion(array $entry, string $minecraftVersion): bool
    {
        foreach ($entry['latestFilesIndexes'] ?? [] as $idx) {
            if (is_array($idx) && (string) ($idx['gameVersion'] ?? '') === $minecraftVersion) {
                return true;
            }
        }
        foreach ($entry['latestFiles'] ?? [] as $file) {
            foreach ($file['gameVersions'] ?? [] as $tag) {
                if ((string) $tag === $minecraftVersion) {
                    return true;
                }
            }
        }

        // No version metadata at all → trust CF's own filter and let the
        // hit through (this keeps brand-new packs without published
        // metadata visible).
        return ! isset($entry['latestFilesIndexes']) && ! isset($entry['latestFiles']);
    }

    /**
     * @param  list<string>  $gameVersions
     * @return list<string>
     */
    private function extractLoadersFromGameVersions(array $gameVersions): array
    {
        $loaders = [];
        foreach ($gameVersions as $tag) {
            $lower = strtolower((string) $tag);
            foreach (['forge', 'fabric', 'quilt', 'neoforge'] as $candidate) {
                if ($lower === $candidate) {
                    $loaders[] = $candidate;
                }
            }
        }

        return array_values(array_unique($loaders));
    }

    /**
     * @param  list<string>  $gameVersions
     * @return list<string>
     */
    private function extractMinecraftVersions(array $gameVersions): array
    {
        $versions = [];
        foreach ($gameVersions as $tag) {
            if (preg_match('/^\d+\.\d+(\.\d+)?$/', (string) $tag) === 1) {
                $versions[] = (string) $tag;
            }
        }

        return array_values(array_unique($versions));
    }

    private function mapReleaseType(int $type): string
    {
        return match ($type) {
            1 => 'release',
            2 => 'beta',
            3 => 'alpha',
            default => 'unknown',
        };
    }

    /**
     * Build a ModpackVersion DTO from a CurseForge file entry. Shared
     * between `listVersions()` (paginated bulk fetch) and `getVersion()`
     * (direct single fetch by id) so both surfaces produce identical
     * shape — the version_label / loaders / minecraft_versions a caller
     * gets shouldn't depend on which path the orchestrator took.
     *
     * @param  array<string, mixed>  $entry
     */
    private function mapFileEntryToVersion(array $entry): ?ModpackVersion
    {
        $fileId = (string) ($entry['id'] ?? '');
        if ($fileId === '') {
            return null;
        }

        return new ModpackVersion(
            versionId: $fileId,
            label: (string) ($entry['displayName'] ?? $entry['fileName'] ?? ''),
            minecraftVersions: $this->extractMinecraftVersions($entry['gameVersions'] ?? []),
            loaders: $this->extractLoadersFromGameVersions($entry['gameVersions'] ?? []),
            releaseType: $this->mapReleaseType((int) ($entry['releaseType'] ?? 1)),
        );
    }

    private function client()
    {
        $headers = ['User-Agent' => $this->userAgent];
        $key = $this->settings->curseforgeApiKey();
        if ($key !== null) {
            $headers['x-api-key'] = $key;
        }

        return $this->http->withHeaders($headers)->timeout(15)->retry(2, 200);
    }
}
