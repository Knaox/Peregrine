<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\DTO;

use Plugins\MinecraftModpackInstaller\Enums\ModpackLoader;

/**
 * Unified search inputs. The route validates every field, so providers may
 * trust them at face value — providers MAY ignore options they don't support
 * (e.g. Technic ignores `sort`/`category`/`loader`).
 *
 * `sort` values are the canonical identifiers documented in
 * {@see ModpackProviderCapabilities::$sortModes}. `null` lets the provider
 * fall back to its native default ordering.
 */
final readonly class SearchCriteria
{
    public function __construct(
        public ?string $query,
        public ?string $minecraftVersion,
        public ?ModpackLoader $loader,
        public int $page,
        public int $pageSize,
        public ?string $sort = null,
        public ?string $category = null,
    ) {}
}
