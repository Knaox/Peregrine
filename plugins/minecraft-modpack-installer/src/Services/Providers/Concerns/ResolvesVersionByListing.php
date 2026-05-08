<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services\Providers\Concerns;

use Plugins\MinecraftModpackInstaller\Services\DTO\ModpackVersion;
use Throwable;

/**
 * Default implementation of `ModpackProviderInterface::getVersion()` for
 * providers that don't expose a direct fetch-by-id endpoint — we just
 * call `listVersions()` and filter for the requested id.
 *
 * Correct but expensive. Providers whose API has a single-call lookup
 * (CurseForge `/files/{id}`, Modrinth `/version/{id}`, FTB
 * `/modpack/{id}/{versionId}`, …) should override `getVersion()` rather
 * than `use` this trait, so the orchestrator's POST `/installation`
 * call doesn't drift past PHP's max_execution_time on big packs.
 */
trait ResolvesVersionByListing
{
    public function getVersion(string $modpackId, string $versionId): ?ModpackVersion
    {
        try {
            $versions = $this->listVersions($modpackId, null);
        } catch (Throwable) {
            return null;
        }

        foreach ($versions as $version) {
            if ($version->versionId === $versionId) {
                return $version;
            }
        }

        return null;
    }
}
