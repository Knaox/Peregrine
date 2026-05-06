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

final class AtlauncherProvider implements ModpackProviderInterface
{
    private const ENDPOINT = 'https://api.atlauncher.com/v2/graphql';

    public function __construct(
        private readonly Factory $http,
        private readonly Repository $cache,
        private readonly string $userAgent,
    ) {}

    public function id(): ModpackProvider
    {
        return ModpackProvider::Atlauncher;
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
            serverMarker: false,
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
        $term = $criteria->query ?? '';
        $query = <<<'GQL'
        query Search($term: String!) {
            packs(search: $term, first: 30) {
                edges {
                    node {
                        id
                        name
                        safeName
                        description
                        versions {
                            version
                            minecraftVersion
                        }
                    }
                }
            }
        }
        GQL;

        try {
            $response = $this->graphql($query, ['term' => $term]);
        } catch (Throwable $e) {
            throw new ProviderRequestException($this->id(), 'search failed: '.$e->getMessage(), $e);
        }

        $edges = $response['data']['packs']['edges'] ?? [];
        $hits = [];
        foreach ($edges as $edge) {
            $node = $edge['node'] ?? [];
            $safeName = (string) ($node['safeName'] ?? '');
            $hits[] = new ModpackSummary(
                provider: $this->id(),
                modpackId: $safeName !== '' ? $safeName : (string) ($node['id'] ?? ''),
                name: (string) ($node['name'] ?? ''),
                slug: $safeName !== '' ? $safeName : null,
                description: $node['description'] ?? null,
                iconUrl: $safeName !== ''
                    ? "https://cdn.atlauncher.com/launcher/images/{$safeName}.png"
                    : null,
                externalUrl: $safeName !== ''
                    ? "https://atlauncher.com/pack/{$safeName}"
                    : null,
                isServerCompatible: null,
            );
        }

        return new SearchResult($hits, count($hits), 1, max(count($hits), 1));
    }

    public function getModpack(string $modpackId): ?ModpackSummary
    {
        $key = 'modpacks:atlauncher:meta:'.sha1($modpackId);

        return $this->cache->remember($key, 24 * 3600, function () use ($modpackId): ?ModpackSummary {
            $query = <<<'GQL'
            query Pack($safe: String!) {
                packBySafeName(safeName: $safe) {
                    id
                    name
                    safeName
                    description
                }
            }
            GQL;

            try {
                $response = $this->graphql($query, ['safe' => $modpackId]);
            } catch (Throwable) {
                return null;
            }

            $node = $response['data']['packBySafeName'] ?? null;
            if (! is_array($node) || empty($node['id'])) {
                return null;
            }

            $safeName = (string) ($node['safeName'] ?? $modpackId);

            return new ModpackSummary(
                provider: $this->id(),
                modpackId: $safeName,
                name: (string) ($node['name'] ?? $modpackId),
                slug: $safeName,
                description: $node['description'] ?? null,
                iconUrl: "https://cdn.atlauncher.com/launcher/images/{$safeName}.png",
                externalUrl: "https://atlauncher.com/pack/{$safeName}",
                isServerCompatible: null,
            );
        });
    }

    /** @return list<ModpackVersion> */
    public function listVersions(string $modpackId, ?string $minecraftVersion): array
    {
        $query = <<<'GQL'
        query Pack($safe: String!) {
            packBySafeName(safeName: $safe) {
                versions {
                    version
                    minecraftVersion
                }
            }
        }
        GQL;

        try {
            $response = $this->graphql($query, ['safe' => $modpackId]);
        } catch (Throwable $e) {
            throw new ProviderRequestException($this->id(), 'listVersions failed: '.$e->getMessage(), $e);
        }

        $versions = $response['data']['packBySafeName']['versions'] ?? [];
        $out = [];
        foreach ($versions as $entry) {
            $mc = (string) ($entry['minecraftVersion'] ?? '');
            $version = (string) ($entry['version'] ?? '');
            if ($minecraftVersion !== null && $mc !== '' && $mc !== $minecraftVersion) {
                continue;
            }
            $out[] = new ModpackVersion(
                versionId: $version,
                label: $version,
                minecraftVersions: $mc !== '' ? [$mc] : [],
                loaders: [],
                releaseType: 'release',
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function graphql(string $query, array $variables): array
    {
        $response = $this->http
            ->withHeaders(['User-Agent' => $this->userAgent, 'Accept' => 'application/json'])
            ->timeout(15)
            ->retry(2, 200)
            ->post(self::ENDPOINT, ['query' => $query, 'variables' => $variables])
            ->throw()
            ->json();

        if (! is_array($response)) {
            throw new ProviderRequestException($this->id(), 'unexpected GraphQL response shape');
        }

        return $response;
    }
}
