<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plugin extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'plugin_id',
        'is_active',
        'settings',
        'version',
        'installed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'settings' => 'array',
            'installed_at' => 'datetime',
        ];
    }
}
