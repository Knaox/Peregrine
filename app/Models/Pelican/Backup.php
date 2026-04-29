<?php

namespace App\Models\Pelican;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Backup extends Model
{
    use SoftDeletes;

    protected $table = 'pelican_backups';

    /** @var list<string> */
    protected $fillable = [
        'pelican_backup_id',
        'server_id',
        'uuid',
        'name',
        'disk',
        'is_successful',
        'is_locked',
        'checksum',
        'bytes',
        'completed_at',
        'pelican_created_at',
        'pelican_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pelican_backup_id' => 'integer',
            'server_id' => 'integer',
            'is_successful' => 'boolean',
            'is_locked' => 'boolean',
            'bytes' => 'integer',
            'completed_at' => 'datetime',
            'pelican_created_at' => 'datetime',
            'pelican_updated_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
