<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable named bundle of Pelican resource limits — RAM, CPU, disk,
 * swap, I/O weight, cpu_pinning. Multiple `ServerConfiguration` rows
 * can point at the same template ; editing the template propagates the
 * new specs to every configuration bound to it (and to every shop
 * subscribed to the corresponding `configuration.updated` webhook).
 *
 * Naming convention :
 *  - human-readable, marketing-style ("Medium-Medium", "Performance-Large").
 *  - UNIQUE at the DB level so collisions surface at save time.
 *
 * Deletion :
 *  - The FK on `server_configurations.resource_template_id` is
 *    `nullOnDelete` — deleting a template orphan-detaches every config
 *    that referenced it (the configs keep running, they just lose their
 *    spec link until an admin re-attaches one). Filament UX should warn
 *    before deleting a template still in use.
 */
class ResourceTemplate extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'ram',
        'cpu',
        'disk',
        'swap_mb',
        'io_weight',
        'cpu_pinning',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ram' => 'integer',
            'cpu' => 'integer',
            'disk' => 'integer',
            'swap_mb' => 'integer',
            'io_weight' => 'integer',
        ];
    }

    /**
     * Configurations currently bound to this template. Used by the
     * Filament admin to confirm before delete and to show usage counts.
     */
    public function serverConfigurations(): HasMany
    {
        return $this->hasMany(ServerConfiguration::class, 'resource_template_id');
    }

    protected static function booted(): void
    {
        // Fans out `configuration.updated` webhooks to every bound
        // configuration when the template's specs change.
        static::observe(\App\Observers\ResourceTemplateObserver::class);
    }
}
