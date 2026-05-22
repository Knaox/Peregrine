<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-model row mirroring one on-disk template JSON (table
 * `easy_config_templates`). Rebuilt by the TemplateRegistry; never the source
 * of truth (the JSON file is). Holds no config values — only render-schema
 * metadata used for listing and egg lookups.
 *
 * @property string $template_id
 * @property array<string,string> $name
 * @property list<int> $target_eggs
 * @property bool $boost_enabled
 */
class TemplateCache extends Model
{
    protected $table = 'easy_config_templates';

    protected $fillable = [
        'template_id',
        'version',
        'name',
        'description',
        'author',
        'target_eggs',
        'boost_enabled',
        'boost_blacklist',
        'file_count',
        'source_path',
        'checksum',
        'is_valid',
        'last_error',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'target_eggs' => 'array',
        'boost_blacklist' => 'array',
        'boost_enabled' => 'boolean',
        'is_valid' => 'boolean',
        'file_count' => 'integer',
    ];

    /** Valid templates whose target_eggs include the given egg id. */
    public function scopeForEgg(Builder $query, int $eggId): Builder
    {
        return $query->where('is_valid', true)->whereJsonContains('target_eggs', $eggId);
    }
}
