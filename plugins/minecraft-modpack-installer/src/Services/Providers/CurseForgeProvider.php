<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\Providers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Plugins\MinecraftModpackInstaller\Enums\ModpackLoader;
use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Exceptions\ProviderRequestException;
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

    /** Loader name → CurseForge numeric id. */
    private const LOADER_MAP = [
        ModpackLoader::Forge->value => 1,
        ModpackLoader::Fabric->value => 4,
        ModpackLoader::Quilt->value => 5,
        ModpackLoader::NeoForge->value => 6,
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

    public function search(SearchCriteria $criteria): SearchResult
    {
        $index = max(0, ($criteria->page - 1) * $criteria->pageSize);
        if ($index + $criteria->pageSize > self::RESULT_CAP) {
            return new SearchResult(hits: [], total: 0, currentPage: $criteria->page, perPage: $criteria->pageSize);
        }

        $params = [
            'gameId' => self::GAME_ID,
            'classId' => self::CLASS_ID_MODPACK,
            'index' => $index,
            'pageSize' => min($criteria->pageSize, 50),
            'sortField' => 2,
            'sortOrder' => 'desc',
        ];
        if ($criteria->query !== null && $criteria->query !== '') {
            $params['searchFilter'] = $criteria->query;
        }
        if ($criteria->minecraftVersion !== null) {
            $params['gameVersion'] = $criteria->minecraftVersion;
        }
        if ($criteria->loader !== null && isset(self::LOADER_MAP[$criteria->loader->value])) {
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

        $key = 'modpacks:curseforge:meta:'.sha1($modpackId);

        return $this->cache->remember($key, 24 * 3600, function () use ($modpackId): ?ModpackSummary {
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

            return new ModpackSummary(
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
        });
    }

    /** @return list<ModpackVersion> */
    public function listVersions(string $modpackId, ?string $minecraftVersion): array
    {
        $params = ['pageSize' => 50, 'index' => 0];
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

        $versions = [];
        foreach ($response['data'] ?? [] as $entry) {
            $loaders = $this->extractLoadersFromGameVersions($entry['gameVersions'] ?? []);
            $minecraftVersions = $this->extractMinecraftVersions($entry['gameVersions'] ?? []);

            $versions[] = new ModpackVersion(
                versionId: (string) $entry['id'],
                label: (string) ($entry['displayName'] ?? $entry['fileName'] ?? ''),
                minecraftVersions: $minecraftVersions,
                loaders: $loaders,
                releaseType: $this->mapReleaseType((int) ($entry['releaseType'] ?? 1)),
            );
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
