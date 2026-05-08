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

final class TechnicProvider implements ModpackProviderInterface
{
    use \Plugins\MinecraftModpackInstaller\Services\Providers\Concerns\ResolvesVersionByListing;

    private const BASE_URL = 'https://api.technicpack.net';

    private const FALLBACK_BUILD = '746';

    public function __construct(
        private readonly Factory $http,
        private readonly Repository $cache,
        private readonly string $userAgent,
    ) {}

    public function id(): ModpackProvider
    {
        return ModpackProvider::Technic;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function capabilities(): ModpackProviderCapabilities
    {
        // Technic's reverse-engineered API caps `/search` at 15 results
        // and exposes no sort/filter knobs — the unified UI hides those
        // controls when this provider is active.
        return new ModpackProviderCapabilities(
            search: true,
            pagination: false,
            minecraftVersionFilter: false,
            loaderFilter: false,
            serverMarker: false,
            multipleVersions: true,
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
        $build = $this->launcherBuild();
        $term = $criteria->query ?? '';

        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/search', ['q' => $term, 'build' => $build])
                ->throw()
                ->json();
        } catch (Throwable $e) {
            throw new ProviderRequestException($this->id(), 'search failed: '.$e->getMessage(), $e);
        }

        $hits = [];
        $entries = $response['modpacks'] ?? $response ?? [];
        if (! is_array($entries)) {
            $entries = [];
        }
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $slug = (string) ($entry['name'] ?? $entry['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $hits[] = new ModpackSummary(
                provider: $this->id(),
                modpackId: $slug,
                name: (string) ($entry['display_name'] ?? $slug),
                slug: $slug,
                description: null,
                iconUrl: $entry['icon'] ?? $entry['logo'] ?? null,
                externalUrl: $entry['url'] ?? "https://www.technicpack.net/modpack/{$slug}",
                isServerCompatible: null,
            );
        }

        return new SearchResult($hits, count($hits), 1, max(count($hits), 1));
    }

    public function getModpack(string $modpackId): ?ModpackSummary
    {
        // Cache successes only — see CurseForgeProvider::getModpack.
        $key = 'modpacks:technic:meta:'.sha1($modpackId);
        $cached = $this->cache->get($key);
        if ($cached instanceof ModpackSummary) {
            return $cached;
        }

        $build = $this->launcherBuild();

        try {
            $response = $this->client()
                ->get(self::BASE_URL.'/modpack/'.rawurlencode($modpackId), ['build' => $build])
                ->throw()
                ->json();
        } catch (Throwable) {
            return null;
        }

        if (! is_array($response) || empty($response['name'])) {
            return null;
        }

        $slug = (string) ($response['name'] ?? $modpackId);

        $summary = new ModpackSummary(
            provider: $this->id(),
            modpackId: $slug,
            name: (string) ($response['displayName'] ?? $response['display_name'] ?? $slug),
            slug: $slug,
            description: $response['description'] ?? null,
            iconUrl: $response['icon']['url']
                ?? $response['logo']['url']
                ?? $response['icon']
                ?? $response['logo']
                ?? null,
            externalUrl: $response['url'] ?? "https://www.technicpack.net/modpack/{$slug}",
            isServerCompatible: null,
        );

        $this->cache->put($key, $summary, 24 * 3600);

        return $summary;
    }

    /** @return list<ModpackVersion> */
    public function listVersions(string $modpackId, ?string $minecraftVersion): array
    {
        $build = $this->launcherBuild();

        try {
            $response = $this->client()
                ->get(self::BASE_URL."/modpack/{$modpackId}", ['build' => $build])
                ->throw()
                ->json();
        } catch (Throwable $e) {
            throw new ProviderRequestException($this->id(), 'listVersions failed: '.$e->getMessage(), $e);
        }

        $latest = (string) ($response['latest'] ?? '');
        $recommended = (string) ($response['recommended'] ?? '');
        $builds = $response['builds'] ?? [];
        if (! is_array($builds) || $builds === []) {
            $builds = array_values(array_filter([$recommended, $latest]));
        }

        $out = [];
        foreach ($builds as $b) {
            $b = (string) $b;
            $label = $b;
            if ($b === $recommended) {
                $label .= ' (recommended)';
            } elseif ($b === $latest) {
                $label .= ' (latest)';
            }
            $out[] = new ModpackVersion(
                versionId: $b,
                label: $label,
                minecraftVersions: [],
                loaders: [],
                releaseType: 'release',
            );
        }

        return $out;
    }

    private function launcherBuild(): string
    {
        return $this->cache->remember(
            'modpacks:technic:launcher_build',
            24 * 3600,
            function (): string {
                try {
                    $response = $this->client()
                        ->get(self::BASE_URL.'/launcher/version/stable4')
                        ->throw()
                        ->json();
                    $build = (string) ($response['build'] ?? '');
                    if ($build !== '') {
                        return $build;
                    }
                } catch (Throwable) {
                    // fall through
                }

                return self::FALLBACK_BUILD;
            },
        );
    }

    private function client()
    {
        return $this->http
            ->withHeaders(['User-Agent' => $this->userAgent, 'Accept' => 'application/json'])
            ->timeout(15)
            ->retry(2, 200);
    }
}
