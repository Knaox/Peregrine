<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\Providers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Exceptions\ProviderRequestException;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackProviderCapabilities;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackSummary;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackVersion;
use Plugins\MinecraftModpackInstaller\Services\DTO\SearchCriteria;
use Plugins\MinecraftModpackInstaller\Services\DTO\SearchResult;
use Plugins\MinecraftModpackInstaller\Services\Providers\Contracts\ModpackProviderInterface;
use Throwable;

final class VoidsWrathProvider implements ModpackProviderInterface
{
    use \Plugins\MinecraftModpackInstaller\Services\Providers\Concerns\ResolvesVersionByListing;

    private const CATALOG_URL = 'https://raw.githubusercontent.com/astrooom/minecraft-modpack-index/main/voidswrath-modpacks.json';

    public function __construct(
        private readonly Factory $http,
        private readonly Repository $cache,
        private readonly string $userAgent,
    ) {}

    public function id(): ModpackProvider
    {
        return ModpackProvider::VoidsWrath;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function capabilities(): ModpackProviderCapabilities
    {
        // The VoidsWrath catalog is a static JSON file with 6 entries;
        // sort/filter is academic, but we expose `relevance` for UI parity.
        return new ModpackProviderCapabilities(
            search: true,
            pagination: false,
            minecraftVersionFilter: false,
            loaderFilter: false,
            serverMarker: true,
            multipleVersions: false,
            sortModes: ['relevance'],
            categoryFilter: false,
        );
    }

    /** @return list<string> */
    public function listMinecraftVersions(): array
    {
        return [];
    }

    /** @return list<\Plugins\MinecraftModpackInstaller\Services\DTO\ModpackCategory> */
    public function listCategories(): array
    {
        return [];
    }

    public function search(SearchCriteria $criteria): SearchResult
    {
        $catalog = $this->loadCatalog();

        $term = strtolower($criteria->query ?? '');
        $hits = [];

        foreach ($catalog as $entry) {
            $name = (string) ($entry['displayName'] ?? '');
            if ($term !== '' && ! str_contains(strtolower($name), $term)) {
                continue;
            }

            $hits[] = new ModpackSummary(
                provider: $this->id(),
                modpackId: (string) ($entry['id'] ?? ''),
                name: $name,
                slug: null,
                description: $entry['description'] ?? null,
                iconUrl: $entry['logo'] ?? null,
                externalUrl: $entry['platformUrl'] ?? null,
                isServerCompatible: ! empty($entry['serverPackUrl']),
            );
        }

        return new SearchResult($hits, count($hits), 1, max(count($hits), 1));
    }

    public function getModpack(string $modpackId): ?ModpackSummary
    {
        $catalog = $this->loadCatalog();
        foreach ($catalog as $entry) {
            if ((string) ($entry['id'] ?? '') !== $modpackId) {
                continue;
            }

            return new ModpackSummary(
                provider: $this->id(),
                modpackId: (string) $entry['id'],
                name: (string) ($entry['displayName'] ?? $modpackId),
                slug: null,
                description: $entry['description'] ?? null,
                iconUrl: $entry['logo'] ?? null,
                externalUrl: $entry['platformUrl'] ?? null,
                isServerCompatible: ! empty($entry['serverPackUrl']),
            );
        }

        return null;
    }

    /** @return list<ModpackVersion> */
    public function listVersions(string $modpackId, ?string $minecraftVersion): array
    {
        $catalog = $this->loadCatalog();
        foreach ($catalog as $entry) {
            if ((string) ($entry['id'] ?? '') !== $modpackId) {
                continue;
            }
            $mc = (string) ($entry['minecraftVersion'] ?? '');
            $mcs = $mc !== '' ? [$mc] : [];

            return [
                new ModpackVersion(
                    versionId: 'latest',
                    label: 'Latest',
                    minecraftVersions: $mcs,
                    loaders: [],
                    releaseType: 'release',
                ),
            ];
        }

        return [];
    }

    /** @return list<array<string, mixed>> */
    private function loadCatalog(): array
    {
        // Cache successes only — the previous `cache->remember` form
        // would happily pin an empty catalog for 24h if a single fetch
        // returned a malformed payload, leaving every modpack lookup
        // returning null until the TTL expired.
        $key = 'modpacks:voidswrath:catalog';
        $cached = $this->cache->get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            $response = $this->http
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->timeout(20)
                ->retry(2, 300)
                ->get(self::CATALOG_URL)
                ->throw()
                ->json();
        } catch (Throwable $e) {
            throw new ProviderRequestException($this->id(), 'catalog fetch failed: '.$e->getMessage(), $e);
        }

        if (! is_array($response) || $response === []) {
            return [];
        }

        $catalog = array_values($response);
        $this->cache->put($key, $catalog, 24 * 3600);

        return $catalog;
    }
}
