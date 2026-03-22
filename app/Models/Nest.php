<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nest extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pelican_nest_id',
        'name',
        'description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pelican_nest_id' => 'integer',
        ];
    }

    /**
     * Get the eggs belonging to this nest.
     */
    public function eggs(): HasMany
    {
        return $this->hasMany(Egg::class);
    }
}
