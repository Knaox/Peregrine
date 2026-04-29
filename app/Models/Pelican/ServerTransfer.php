<?php

namespace App\Models\Pelican;

use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServerTransfer extends Model
{
    protected $table = 'pelican_server_transfers';

    /** @var list<string> */
    protected $fillable = [
        'pelican_server_transfer_id',
        'server_id',
        'successful',
        'old_node',
        'new_node',
        'old_allocation',
        'new_allocation',
        'old_additional_allocations',
        'new_additional_allocations',
        'archived',
        'pelican_created_at',
        'pelican_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pelican_server_transfer_id' => 'integer',
            'server_id' => 'integer',
            'successful' => 'boolean',
            'old_node' => 'integer',
            'new_node' => 'integer',
            'old_allocation' => 'integer',
            'new_allocation' => 'integer',
            'old_additional_allocations' => 'array',
            'new_additional_allocations' => 'array',
            'archived' => 'boolean',
            'pelican_created_at' => 'datetime',
            'pelican_updated_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }
}
