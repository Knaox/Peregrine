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
        'scheme',
        'daemon_listen',
        'daemon_token_id',
        'daemon_token',
        'maintenance_mode',
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
            'daemon_listen' => 'integer',
            'daemon_token' => 'encrypted',
            'maintenance_mode' => 'boolean',
        ];
    }

    /**
     * Base URL of the Wings daemon for this node.
     */
    public function daemonBaseUrl(): string
    {
        return sprintf('%s://%s:%d', $this->scheme ?: 'https', $this->fqdn, $this->daemon_listen ?: 8080);
    }

    /**
     * Whether the Wings daemon token has been hydrated for this node.
     */
    public function hasDaemonToken(): bool
    {
        return ($this->daemon_token ?? '') !== '';
    }
}
