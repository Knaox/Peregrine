<?php

namespace Plugins\EggConfigEditor\Models;

use App\Models\Egg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per (set of eggs, file path) pair the admin wants to expose to
 * players. The same file can target multiple eggs (e.g. ARK + ARK Modded
 * + ARK Survival Ascended share the same `GameUserSettings.ini`).
 *
 * Storage : `egg_ids` is a JSON array of local Egg IDs. The controller
 * lists files for a server's egg via `whereJsonContains('egg_ids', $eggId)`.
 */
class EggConfigFile extends Model
{
    protected $table = 'egg_config_files';

    /** @var list<string> */
    protected $fillable = [
        'egg_ids',
        'file_paths',
        'sections',
        'file_type',
        'enabled',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'egg_ids' => 'array',
            'file_paths' => 'array',
            'sections' => 'array',
            'enabled' => 'boolean',
        ];
    }

    /**
     * Eager-load all Eggs referenced by `egg_ids`. There's no Eloquent
     * many-to-many because we use a JSON column instead of a pivot — the
     * trade-off is that we run one extra query per row, but the table is
     * read-rarely (only in the Filament admin) so the simplicity wins.
     *
     * @return Collection<int, Egg>
     */
    public function eggs(): Collection
    {
        $ids = $this->egg_ids ?? [];
        if ($ids === []) {
            return new Collection;
        }
        return Egg::query()->whereIn('id', $ids)->get();
    }

    /**
     * Scope: filter to files exposed for the given egg. Uses MySQL JSON
     * containment (`JSON_CONTAINS`) so we don't need to load + filter
     * in PHP.
     *
     * @param  Builder<EggConfigFile>  $query
     * @return Builder<EggConfigFile>
     */
    public function scopeForEgg(Builder $query, int $eggId): Builder
    {
        return $query->whereJsonContains('egg_ids', $eggId);
    }
}
