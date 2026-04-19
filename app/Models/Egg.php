<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Egg extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pelican_egg_id',
        'nest_id',
        'name',
        'docker_image',
        'banner_image',
        'startup',
        'description',
        'tags',
        'features',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pelican_egg_id' => 'integer',
            'nest_id' => 'integer',
            'tags' => 'array',
            'features' => 'array',
        ];
    }

    /**
     * Get the nest this egg belongs to.
     */
    public function nest(): BelongsTo
    {
        return $this->belongsTo(Nest::class);
    }

    /**
     * Get the servers using this egg.
     */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }
}
