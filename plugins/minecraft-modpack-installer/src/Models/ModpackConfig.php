<?php

declare(strict_types=1);

namespace Plugins\MinecraftModpackInstaller\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row holding every admin-configurable knob of the plugin.
 * `::current()` returns (or creates) the canonical `id = 1` row, so callers
 * never have to think about cardinality. Mirrors the pattern used by
 * `Plugins\ArkModsInstaller\Models\ArkModsConfig` so the Filament admin
 * UI can wrap it as a real Resource.
 */
class ModpackConfig extends Model
{
    protected $table = 'modpack_configs';

    /** @var list<string> */
    protected $fillable = [
        'egg_ids',
        'curseforge_api_key',
        'default_provider',
        'default_sort',
        'page_label',
        'page_route',
        'modpacks_per_page',
        'install_timeout_minutes',
        'cache_ttl_seconds',
        // Java compatibility — overrides over the bundled
        // `config/java-compatibility.php`. All three are nullable: when null
        // / empty the JavaCompatibilityMatrix falls back to the plugin's
        // default config file. See `javaRules()`, `javaImages()`,
        // `defaultJava()` for the merge semantics.
        'java_rules',
        'java_images',
        'default_java',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'egg_ids' => 'array',
            'curseforge_api_key' => 'encrypted',
            'modpacks_per_page' => 'integer',
            'install_timeout_minutes' => 'integer',
            'cache_ttl_seconds' => 'integer',
            'java_rules' => 'array',
            'java_images' => 'array',
            'default_java' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::firstOrCreate(
            ['id' => 1],
            [
                'egg_ids' => [],
                'curseforge_api_key' => null,
                'default_provider' => 'modrinth',
                'default_sort' => 'relevance',
                'page_label' => null,
                'page_route' => '/modpacks',
                'modpacks_per_page' => 25,
                'install_timeout_minutes' => 30,
                'cache_ttl_seconds' => 3600,
                'java_rules' => null,
                'java_images' => null,
                'default_java' => null,
            ],
        );
    }

    /** @return list<int> */
    public function eggIdsList(): array
    {
        $raw = $this->egg_ids;
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(
            array_map('intval', $raw),
            static fn (int $id): bool => $id > 0,
        ));
    }

    public function modpacksPerPage(): int
    {
        $value = (int) ($this->modpacks_per_page ?: 25);

        // Page-size whitelist bumped from [6, 12, 24] to [10, 25, 50] on
        // 2026-05-08 to match operator demand for denser modpack listings.
        // Legacy stored values fall back to the new default 25 instead of
        // returning their old (now-invalid) value, so the SPA never shows
        // a page size the admin form can't display.
        return in_array($value, [10, 25, 50], true) ? $value : 25;
    }

    public function installTimeoutMinutes(): int
    {
        $raw = (int) ($this->install_timeout_minutes ?: 30);

        return max(5, min(180, $raw));
    }

    public function cacheTtlSeconds(): int
    {
        $raw = (int) ($this->cache_ttl_seconds ?: 3600);

        return max(60, min(86400, $raw));
    }
}
