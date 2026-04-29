<?php

namespace App\Models\Pelican;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Mirror metadata for a Pelican database. Plaintext password is NEVER
 * stored — to surface it in the UI, the controller fetches live via
 * Pelican Client API per-request.
 *
 * Note: classname intentionally `Database` (matches Pelican's model);
 * always reference via FQN `\App\Models\Pelican\Database` to avoid the
 * Laravel `Database` facade collision.
 */
class Database extends Model
{
    use SoftDeletes;

    protected $table = 'pelican_databases';

    /** @var list<string> */
    protected $fillable = [
        'pelican_database_id',
        'server_id',
        'pelican_database_host_id',
        'database',
        'username',
        'remote',
        'max_connections',
        'pelican_created_at',
        'pelican_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pelican_database_id' => 'integer',
            'server_id' => 'integer',
            'pelican_database_host_id' => 'integer',
            'max_connections' => 'integer',
            'pelican_created_at' => 'datetime',
            'pelican_updated_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(DatabaseHost::class, 'pelican_database_host_id');
    }
}
