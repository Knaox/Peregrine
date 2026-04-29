<?php

namespace App\Models\Pelican;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Allocation extends Model
{
    use SoftDeletes;

    protected $table = 'pelican_allocations';

    /** @var list<string> */
    protected $fillable = [
        'pelican_allocation_id',
        'node_id',
        'server_id',
        'ip',
        'port',
        'ip_alias',
        'notes',
        'is_locked',
        'is_default',
        'pelican_created_at',
        'pelican_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pelican_allocation_id' => 'integer',
            'node_id' => 'integer',
            'server_id' => 'integer',
            'port' => 'integer',
            'is_locked' => 'boolean',
            'is_default' => 'boolean',
            'pelican_created_at' => 'datetime',
            'pelican_updated_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
