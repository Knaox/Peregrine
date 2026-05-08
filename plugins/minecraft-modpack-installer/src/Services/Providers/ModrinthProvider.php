<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\Providers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Exceptions\ProviderRequestException;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackCategory;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackProviderCapabilities;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackSummary;
use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackVersion;
use Plugins\MinecraftModpackInstaller\Services\DTO\SearchCriteria;
use Plugins\MinecraftModpackInstaller\Services\DTO\SearchResult;
use Plugins\MinecraftModpackInstaller\Services\Providers\Contracts\ModpackProviderInterface;
use Throwable;

final class ModrinthProvider implements ModpackProviderInterface
{
    use \Plugins\MinecraftModpackInstaller\Services\Providers\Concerns\ResolvesVersionByListing;

    private const BASE_URL = 'https://api.modrinth.com/v2';

    /**
     * Canonical sort id → Modrinth `index` value. The unified UI exposes the
     * canonical ids; each provider is responsible for its own translation.
     * Modrinth doesn't have a direct "name" sort — we fall back to relevance
     * with the query name and rely on the provider's relevance ranking.
     */
    private const SORT_MAP = [
        'relevance' => 'relevance',
        'popular' => 'downloads',
        'downloads' => 'downloads',
        'updated' => 'updated',
        'newest' => 'newest',
        'follows' => 'follows',
    ];

    public function __construct(
        private readonly Factory $http,
        private readonly Repository $cache,
        private readonly string $userAgent,
    ) {}

    public function id(): ModpackProvider
    {
        return ModpackProvider::Modrinth;
    }

    public function isConfigured(): bool
    {
        return true;
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
            sortModes: ['relevance', 'popular', 'downloads', 'updated', 'newest', 'follows'],
            categoryFilter: true,
        );
    }

    /** @return list<string> */
    public function listMinecraftVersions(): array
    {
        return $this->cache->remember(
            'modpacks:modrinth:mc-versions',
            6 * 3600,
            fn (): array => $this->fetchMinecraftVersions(),
        );
    }

    /** @return list<ModpackCategory> */
    public function listCategories(): array
    {
        return $this->cache->remember(
            'modpacks:modrinth:categories',
            24 * 3600,
            function (): array {
                try {
                    $response = $this->client()
                        ->get(self::BASE_URL.'/tag/category')
                        ->throw()
                        ->json();
                } catch (Throwable) {
                    return [];
                }

                $out = [];
                foreach ($response ?? [] as $entry) {
                    // Filter to project-type=modpack categories so the UI
                    // doesn't surface mod-only buckets like "library" that
                    // never produce results when paired with project_type:modpack.
                    if (! is_array($entry) || ($entry['project_type'] ?? null) !== 'modpack') {
                        continue;
                    }
                    $name = (string) ($entry['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $out[] = new ModpackCategory(
                        id: $name,
                        label: ucwords(str_replace(['-', '_'], ' ', $name)),
                        iconUrl: $entry['icon'] ?? null,
                    );
                }

                usort($out, static fn ($a, $b) => strcasecmp($a->label, $b->label));

                return $out;
            },
        );
    }

    public function search(SearchCriteria $criteria): SearchResult
    {
        $facets = [['project_type:modpack']];
        if ($criteria->minecraftVersion !== null) {
            $facets[] = ["versions:{$criteria->minecraftVersion}"];
        }
        if ($criteria->loader !== null) {
            $facets[] = ["categories:{$criteria->loader->value}"];
        }
        if ($criteria->category !== null && $criteria->category !== '') {
            $facets[] = ["categories:{$criteria->category}"];
        }

        $offset = max(0, ($criteria->page - 1) * $criteria->pageSize);
        $sortIndex = self::SORT_MAP[$criteria->sort ?? 'relevance'] ?? 'relevance';

        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/search', [
                    'query' => $criteria->query ?? '',
                    'facets' => json_encode($facets, JSON_THROW_ON_ERROR),
                    'limit' => $criteria->pageSize,
                    'offset' => $offset,
                    'index' => $sortIndex,
                ])
                ->throw()
                ->json();
        } catch (Throwable $e) {
            throw new ProviderRequestException($this->id(), 'search failed: '.$e->getMessage(), $e);
        }

        $hits = [];
        foreach ($response['hits'] ?? [] as $hit) {
            $hits[] = new ModpackSummary(
                provider: $this->id(),
                modpackId: (string) ($hit['project_id'] ?? $hit['slug'] ?? ''),
                name: (string) ($hit['title'] ?? ''),
                slug: $hit['slug'] ?? null,
                description: $hit['description'] ?? null,
                iconUrl: $hit['icon_url'] ?? null,
                externalUrl: isset($hit['slug'])
                    ? 'https://modrinth.com/modpack/'.$hit['slug']
                    : null,
                isServerCompatible: $this->isServerCompatible($hit['server_side'] ?? null),
            );
        }

        return new SearchResult(
            hits: $hits,
            total: (int) ($response['total_hits'] ?? count($hits)),
            currentPage: $criteria->page,
            perPage: $criteria->pageSize,
        );
    }

    public function getModpack(string $modpackId): ?ModpackSummary
    {
        // Cache successes only — see CurseForgeProvider::getModpack for
        // the rationale (a transient API failure must not pin a null
        // result for 24h and downgrade every later install of the same
        // modpack to its raw id as display name).
        $key = 'modpacks:modrinth:meta:'.sha1($modpackId);
        $cached = $this->cache->get($key);
        if ($cached instanceof ModpackSummary) {
            return $cached;
        }

        try {
            $entry = $this->client()
                ->get(self::BASE_URL.'/project/'.rawurlencode($modpackId))
                ->throw()
                ->json();
        } catch (Throwable) {
            return null;
        }

        if (! is_array($entry) || empty($entry['id'])) {
            return null;
        }

        $slug = $entry['slug'] ?? null;

        $summary = new ModpackSummary(
            provider: $this->id(),
            modpackId: (string) ($entry['id'] ?? $modpackId),
            name: (string) ($entry['title'] ?? $modpackId),
            slug: $slug,
            description: $entry['description'] ?? null,
            iconUrl: $entry['icon_url'] ?? null,
            externalUrl: $slug !== null ? 'https://modrinth.com/modpack/'.$slug : null,
            isServerCompatible: $this->isServerCompatible($entry['server_side'] ?? null),
        );

        $this->cache->put($key, $summary, 24 * 3600);

        return $summary;
    }

    /**
     * Modrinth's `/project/{id}/version` returns the FULL list (no pagination
     * on its side). We pass `featured=false` to get every version including
     * older releases the maintainer didn't pin, and skip the changelog body
     * to keep the payload small.
     *
     * @return list<ModpackVersion>
     */
    public function listVersions(string $modpackId, ?string $minecraftVersion): array
    {
        $params = [];
        if ($minecraftVersion !== null) {
            $params['game_versions'] = json_encode([$minecraftVersion], JSON_THROW_ON_ERROR);
        }

        try {
            $response = $this->client()
                ->get(self::BASE_URL."/project/{$modpackId}/version", $params)
                ->throw()
                ->json();
        } catch (Throwable $e) {
            throw new ProviderRequestException($this->id(), 'listVersions failed: '.$e->getMessage(), $e);
        }

        $versions = [];
        foreach ($response ?? [] as $entry) {
            $loaders = array_values(array_filter(
                array_map('strtolower', $entry['loaders'] ?? []),
                static fn ($l) => in_array($l, ['forge', 'fabric', 'quilt', 'neoforge'], true),
            ));
            $versions[] = new ModpackVersion(
                versionId: (string) ($entry['id'] ?? ''),
                label: (string) ($entry['name'] ?? $entry['version_number'] ?? ''),
                minecraftVersions: array_values(array_map('strval', $entry['game_versions'] ?? [])),
                loaders: $loaders,
                releaseType: (string) ($entry['version_type'] ?? 'unknown'),
            );
        }

        return $versions;
    }

    /** @return list<string> */
    private function fetchMinecraftVersions(): array
    {
        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/tag/game_version')
                ->throw()
                ->json();
        } catch (Throwable) {
            return [];
        }

        // Include both `release` (modern MC: 1.16+) AND `old_release`
        // (legacy MC: 1.7.10, 1.8.9, 1.10.2, 1.12.2 …) — many popular
        // modpacks target legacy versions and were otherwise hidden from
        // the version filter. Keep snapshots/betas out so the list stays
        // useful for users picking a stable target.
        $versions = [];
        foreach ($response ?? [] as $entry) {
            $type = (string) ($entry['version_type'] ?? '');
            if ($type === 'release' || $type === 'old_release') {
                $versions[] = (string) $entry['version'];
            }
        }

        // Newest-first ordering by semver; non-semver falls back to string sort.
        usort($versions, static fn ($a, $b) => version_compare((string) $b, (string) $a));

        return array_values(array_unique($versions));
    }

    private function isServerCompatible(?string $serverSide): ?bool
    {
        return match ($serverSide) {
            'required', 'optional' => true,
            'unsupported' => false,
            default => null,
        };
    }

    private function client()
    {
        return $this->http
            ->withHeaders(['User-Agent' => $this->userAgent])
            ->timeout(15)
            ->retry(2, 200);
    }
}
