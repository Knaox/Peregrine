<?php

namespace Plugins\Invitations\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Plugin-owned mirror of a Pelican subuser. Persisted by the
 * SyncPelicanSubuser listener — core never writes here.
 *
 * Pelican-side `subuser_id` and `server_id` are stored as plain Pelican
 * IDs (no FK to local servers/users tables). We don't need a join: the
 * controller already loads the local Server / User by their pelican_*_id
 * before showing subusers.
 */
final class PelicanSubuser extends Model
{
    protected $table = 'invitations_pelican_subusers';

    /** @var list<string> */
    protected $fillable = [
        'pelican_subuser_id',
        'pelican_server_id',
        'pelican_user_id',
        'email',
        'uuid',
        'username',
        'permissions',
        'pelican_created_at',
        'pelican_updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pelican_subuser_id' => 'integer',
            'pelican_server_id' => 'integer',
            'pelican_user_id' => 'integer',
            'permissions' => 'array',
            'pelican_created_at' => 'datetime',
            'pelican_updated_at' => 'datetime',
        ];
    }
}
