<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pelican_node_id',
        'name',
        'fqdn',
        'memory',
        'disk',
        'location',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pelican_node_id' => 'integer',
            'memory' => 'integer',
            'disk' => 'integer',
        ];
    }
}
