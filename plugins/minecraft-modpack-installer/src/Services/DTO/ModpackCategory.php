<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\DTO;

/**
 * Provider-side category/tag exposed in the unified filter bar. `id` is the
 * native identifier the provider expects on its search endpoint
 * (CurseForge categoryId, Modrinth slug, FTB tag name, …); `label` is the
 * already-localised display string.
 */
final readonly class ModpackCategory
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $iconUrl = null,
    ) {}
}
