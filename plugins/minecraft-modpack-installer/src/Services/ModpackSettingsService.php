<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Services;

use Illuminate\Support\Facades\Cache;
use Plugins\MinecraftModpackInstaller\Enums\ModpackProvider;
use Plugins\MinecraftModpackInstaller\Models\ModpackConfig;

/**
 * Public API consumed by the install jobs, providers, eligibility service,
 * routes, and console commands. Backed by the singleton `modpack_configs`
 * Eloquent row (Filament-managed). Keeps the historical method shape so
 * existing callers don't need to change after the migration from the
 * legacy KeyValue settings table.
 *
 * Reads go straight through Eloquent — the singleton row is one indexed
 * lookup per call, and the encrypted CurseForge key would leak into the
 * cache backend if we tried to memoize the model's casted attributes.
 */
class ModpackSettingsService
{
    public function curseforgeApiKey(): ?string
    {
        $value = ModpackConfig::current()->curseforge_api_key;
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    public function setCurseforgeApiKey(?string $value): void
    {
        $config = ModpackConfig::current();
        $config->curseforge_api_key = ($value === null || $value === '') ? null : $value;
        $config->save();
    }

    /** @return list<int> */
    public function whitelistedEggIds(): array
    {
        return ModpackConfig::current()->eggIdsList();
    }

    /** @param  list<int>  $ids */
    public function setWhitelistedEggIds(array $ids): void
    {
        $clean = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0,
        )));

        $config = ModpackConfig::current();
        $config->egg_ids = $clean;
        $config->save();

        // Manifest enricher caches its read of this list separately so the
        // sidebar doesn't hit the DB on every page load — bust it on save.
        Cache::forget('modpack_settings.whitelisted_egg_ids');
    }

    public function installTimeoutMinutes(): int
    {
        return ModpackConfig::current()->installTimeoutMinutes();
    }

    public function setInstallTimeoutMinutes(int $minutes): void
    {
        $config = ModpackConfig::current();
        $config->install_timeout_minutes = max(5, min(180, $minutes));
        $config->save();
    }

    public function defaultProvider(): ModpackProvider
    {
        $raw = (string) (ModpackConfig::current()->default_provider ?? ModpackProvider::Modrinth->value);

        return ModpackProvider::tryFrom($raw) ?? ModpackProvider::Modrinth;
    }

    public function setDefaultProvider(ModpackProvider $provider): void
    {
        $config = ModpackConfig::current();
        $config->default_provider = $provider->value;
        $config->save();
    }

    public function defaultSort(): string
    {
        $raw = (string) (ModpackConfig::current()->default_sort ?? 'relevance');

        return in_array($raw, ['relevance', 'downloads', 'updated', 'newest'], true)
            ? $raw
            : 'relevance';
    }

    public function modpacksPerPage(): int
    {
        return ModpackConfig::current()->modpacksPerPage();
    }

    public function pageRoute(): string
    {
        $raw = (string) (ModpackConfig::current()->page_route ?? '/modpacks');
        if ($raw === '' || preg_match('/^\/[a-z0-9\-]+$/', $raw) !== 1) {
            return '/modpacks';
        }

        return $raw;
    }

    public function pageLabel(): ?string
    {
        $raw = ModpackConfig::current()->page_label;

        return is_string($raw) && $raw !== '' ? $raw : null;
    }

    public function cacheTtlSeconds(): int
    {
        return ModpackConfig::current()->cacheTtlSeconds();
    }
}
