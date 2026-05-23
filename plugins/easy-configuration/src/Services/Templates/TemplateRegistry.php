<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Services\Templates;

use Illuminate\Support\Collection;
use Plugins\EasyConfiguration\Models\TemplateCache;

/**
 * Bridges the on-disk template files and the `easy_config_templates` cache.
 *
 * `rebuild()` syncs the cache from disk (upserting changed/new files, deleting
 * rows whose file is gone). Listing/egg-lookups read the cache; the full
 * definition is always (re)loaded from disk so the JSON file stays the single
 * source of truth.
 */
final class TemplateRegistry
{
    public function __construct(private readonly TemplateLoader $loader) {}

    public function rebuild(): void
    {
        $existing = TemplateCache::query()->pluck('checksum', 'template_id');
        $seen = [];

        foreach ($this->loader->loadAll() as $loaded) {
            $seen[] = $loaded->id;

            // Skip files whose content is unchanged so the 60s self-heal
            // rebuild stays cheap (no write when nothing moved).
            if (($existing[$loaded->id] ?? null) === $loaded->checksum) {
                continue;
            }

            TemplateCache::query()->updateOrCreate(
                ['template_id' => $loaded->id],
                $this->attributes($loaded),
            );
        }

        TemplateCache::query()
            ->whereNotIn('template_id', $seen === [] ? ['__none__'] : $seen)
            ->delete();
    }

    /** Full, freshly-loaded definition for a template id (null if missing/invalid). */
    public function definition(string $id): ?TemplateDefinition
    {
        $loaded = $this->loader->loadOne($id);

        return $loaded !== null && $loaded->valid ? $loaded->definition : null;
    }

    /** @return Collection<int, TemplateCache> */
    public function forEgg(int $eggId): Collection
    {
        return TemplateCache::query()->forEgg($eggId)->orderBy('template_id')->get();
    }

    /** @return Collection<int, TemplateCache> */
    public function all(): Collection
    {
        return TemplateCache::query()->orderBy('template_id')->get();
    }

    /** Union of egg ids targeted by every valid template (drives the manifest enricher). */
    public function targetedEggIds(): array
    {
        $ids = [];
        foreach (TemplateCache::query()->where('is_valid', true)->pluck('target_eggs') as $eggs) {
            foreach ((array) $eggs as $eggId) {
                $ids[(int) $eggId] = true;
            }
        }

        return array_values(array_map('intval', array_keys($ids)));
    }

    /**
     * Env var names linked by any parameter of every valid template targeting
     * this egg — used by the core to hide them from its startup-variables page.
     *
     * @return list<string>
     */
    public function linkedEnvVars(int $eggId): array
    {
        $vars = [];
        foreach ($this->forEgg($eggId) as $row) {
            $definition = $this->definition($row->template_id);
            if ($definition === null) {
                continue;
            }
            foreach ($definition->files() as $file) {
                foreach ($this->envVarsInParameters(is_array($file['parameters'] ?? null) ? $file['parameters'] : []) as $name) {
                    $vars[$name] = true;
                }
            }
        }

        return array_keys($vars);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    private function envVarsInParameters(array $params): array
    {
        $out = [];
        foreach ($params as $value) {
            if (! is_array($value)) {
                continue;
            }
            if (isset($value['display_type'])) {
                if (! empty($value['env_var'])) {
                    $out[] = (string) $value['env_var'];
                }

                continue;
            }
            foreach ($value as $childDef) {
                if (is_array($childDef) && ! empty($childDef['env_var'])) {
                    $out[] = (string) $childDef['env_var'];
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function attributes(LoadedTemplate $loaded): array
    {
        $definition = $loaded->definition;

        return [
            'version' => $definition?->version() ?? '0.0.0',
            'name' => $definition?->name() ?? ['en' => $loaded->id],
            'description' => $definition?->description() ?? [],
            'author' => $definition?->author(),
            'target_eggs' => $definition?->targetEggs() ?? [],
            'boost_enabled' => $definition?->boostEnabled() ?? false,
            'boost_blacklist' => $definition?->boostBlacklist() ?? [],
            'file_count' => $definition?->fileCount() ?? 0,
            'source_path' => $loaded->sourcePath,
            'checksum' => $loaded->checksum,
            'is_valid' => $loaded->valid,
            'last_error' => $loaded->error,
        ];
    }
}
