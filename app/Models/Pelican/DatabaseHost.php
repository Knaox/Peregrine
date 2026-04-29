<?php

namespace App\Models\Pelican;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DatabaseHost extends Model
{
    use SoftDeletes;

    protected $table = 'pelican_database_hosts';

    /** @var list<string> */
    protected $fillable = [
        'pelican_database_host_id',
        'name',
        'host',
        'port',
        'username',
        'max_databases',
        'pelican_created_at',
        'pelican_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pelican_database_host_id' => 'integer',
            'port' => 'integer',
            'max_databases' => 'integer',
            'pelican_created_at' => 'datetime',
            'pelican_updated_at' => 'datetime',
        ];
    }

    public function databases(): HasMany
    {
        return $this->hasMany(Database::class, 'pelican_database_host_id');
    }
}
