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

final class FtbProvider implements ModpackProviderInterface
{
    private const BASE_URL = 'https://api.modpacks.ch';

    /** Internal modpack IDs known to be uninteresting/internal. */
    private const FILTERED_IDS = [81];

    public function __construct(
        private readonly Factory $http,
        private readonly Repository $cache,
        private readonly string $userAgent,
    ) {}

    public function id(): ModpackProvider
    {
        return ModpackProvider::Ftb;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function capabilities(): ModpackProviderCapabilities
    {
        return new ModpackProviderCapabilities(
            search: true,
            pagination: false,
            minecraftVersionFilter: false,
            loaderFilter: false,
            serverMarker: true,
            multipleVersions: true,
        );
    }

    /** @return list<string> */
    public function listMinecraftVersions(): array
    {
        return [];
    }

    public function search(SearchCriteria $criteria): SearchResult
    {
        try {
            $term = $criteria->query ?? '';
            $endpoint = $term !== ''
                ? self::BASE_URL.'/public/modpack/search/30?term='.urlencode($term)
                : self::BASE_URL.'/public/modpack/popular/installs/30';

            $response = $this->client()->get($endpoint)->throw()->json();
        } catch (Throwable $e) {
            throw new ProviderRequestException($this->id(), 'search failed: '.$e->getMessage(), $e);
        }

        // FTB returns 200 OK with `{"status":"error","message":"..."}` on
        // problems instead of a 4xx — treat the error envelope as no results
        // rather than letting it pass through as a real (empty) catalogue.
        if (is_array($response) && ($response['status'] ?? null) === 'error') {
            throw new ProviderRequestException(
                $this->id(),
                'FTB error: '.(string) ($response['message'] ?? 'unknown'),
            );
        }

        $ids = array_values(array_filter(
            $response['packs'] ?? [],
            static fn ($id) => ! in_array((int) $id, self::FILTERED_IDS, true),
        ));

        $hits = [];
        foreach ($ids as $id) {
            $detail = $this->fetchDetail((int) $id);
            if ($detail === null) {
                continue;
            }
            $hits[] = $detail;
        }

        return new SearchResult($hits, count($hits), 1, max(count($hits), 1));
    }

    public function getModpack(string $modpackId): ?ModpackSummary
    {
        if (! ctype_digit($modpackId)) {
            return null;
        }

        return $this->fetchDetail((int) $modpackId);
    }

    /** @return list<ModpackVersion> */
    public function listVersions(string $modpackId, ?string $minecraftVersion): array
    {
        try {
            $response = $this->client()
                ->get(self::BASE_URL."/public/modpack/{$modpackId}")
                ->throw()
                ->json();
        } catch (Throwable $e) {
            throw new ProviderRequestException($this->id(), 'listVersions failed: '.$e->getMessage(), $e);
        }

        if (is_array($response) && ($response['status'] ?? null) === 'error') {
            throw new ProviderRequestException(
                $this->id(),
                'FTB error: '.(string) ($response['message'] ?? 'unknown'),
            );
        }

        $versions = $response['versions'] ?? [];
        $out = [];
        foreach ($versions as $version) {
            $manifest = $this->fetchVersionManifest((int) $modpackId, (int) ($version['id'] ?? 0));
            $mcVersions = [];
            $loaders = [];

            if ($manifest !== null) {
                foreach ($manifest['targets'] ?? [] as $target) {
                    $name = strtolower((string) ($target['name'] ?? ''));
                    $value = (string) ($target['version'] ?? '');
                    if ($name === 'minecraft' && $value !== '') {
                        $mcVersions[] = $value;
                    }
                    if (in_array($name, ['forge', 'fabric', 'quilt', 'neoforge'], true)) {
                        $loaders[] = $name;
                    }
                }
            }

            if ($minecraftVersion !== null && ! in_array($minecraftVersion, $mcVersions, true)) {
                continue;
            }

            $out[] = new ModpackVersion(
                versionId: (string) ($version['id'] ?? ''),
                label: (string) ($version['name'] ?? ''),
                minecraftVersions: array_values(array_unique($mcVersions)),
                loaders: array_values(array_unique($loaders)),
                releaseType: strtolower((string) ($version['type'] ?? 'release')),
            );
        }

        return $out;
    }

    private function fetchDetail(int $id): ?ModpackSummary
    {
        return $this->cache->remember(
            "modpacks:ftb:detail:{$id}",
            900,
            function () use ($id): ?ModpackSummary {
                try {
                    $response = $this->client()
                        ->get(self::BASE_URL."/public/modpack/{$id}")
                        ->throw()
                        ->json();
                } catch (Throwable) {
                    return null;
                }

                if (is_array($response) && ($response['status'] ?? null) === 'error') {
                    return null;
                }

                $art = null;
                foreach ($response['art'] ?? [] as $entry) {
                    if (($entry['type'] ?? '') === 'square') {
                        $art = (string) $entry['url'];
                        break;
                    }
                }
                $art ??= ($response['art'][0]['url'] ?? null);

                return new ModpackSummary(
                    provider: ModpackProvider::Ftb,
                    modpackId: (string) ($response['id'] ?? $id),
                    name: (string) ($response['name'] ?? ''),
                    slug: null,
                    description: $response['synopsis'] ?? null,
                    iconUrl: $art,
                    externalUrl: 'https://feed-the-beast.com/modpacks/'.($response['id'] ?? $id),
                    isServerCompatible: true,
                );
            },
        );
    }

    /** @return array<string, mixed>|null */
    private function fetchVersionManifest(int $modpackId, int $versionId): ?array
    {
        if ($versionId === 0) {
            return null;
        }

        try {
            $response = $this->client()
                ->get(self::BASE_URL."/public/modpack/{$modpackId}/{$versionId}")
                ->throw()
                ->json();
        } catch (Throwable) {
            return null;
        }

        if (is_array($response) && ($response['status'] ?? null) === 'error') {
            return null;
        }

        return is_array($response) ? $response : null;
    }

    private function client()
    {
        return $this->http
            ->withHeaders(['User-Agent' => $this->userAgent, 'Accept' => 'application/json'])
            ->timeout(15)
            ->retry(2, 200);
    }
}
