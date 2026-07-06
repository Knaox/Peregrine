<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Server extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pelican_server_id',
        'identifier',
        'pelican_uuid',
        'name',
        'status',
        'egg_id',
        'node_id',
        'server_configuration_id',
        'external_order_id',
        'stripe_subscription_id',
        'payment_intent_id',
        'paymenter_service_id',
        'idempotency_key',
        'provisioning_error',
        'scheduled_deletion_at',
        'scheduled_suspension_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'pelican_server_id' => 'integer',
            'status' => 'string',
            'egg_id' => 'integer',
            'node_id' => 'integer',
            'server_configuration_id' => 'integer',
            'scheduled_deletion_at' => 'datetime',
            'scheduled_suspension_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this server.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the egg used by this server.
     */
    public function egg(): BelongsTo
    {
        return $this->belongsTo(Egg::class);
    }

    /**
     * Local mirror of the Pelican node hosting this server. Nullable —
     * hydrated at provisioning or lazily by ResolveServerNodeAction.
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /**
     * Get the technical configuration this server was provisioned from.
     */
    public function serverConfiguration(): BelongsTo
    {
        return $this->belongsTo(ServerConfiguration::class, 'server_configuration_id');
    }

    /**
     * All users with access to this server (owners + subusers).
     */
    public function accessUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'server_user')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    /**
     * Query scope: restrict to servers the given user can access, with an
     * admin bypass.
     *
     * Replaces the `whereHas('accessUsers', fn($q) => $q->where('user_id', $userId))`
     * pattern used in the invitations plugin (and anywhere else that needs
     * "this user sees this server"). Admins skip the pivot check — consistent
     * with the scoped Gate::before whitelist (AuthServiceProvider, plan §S5).
     *
     * @param  Builder<Server>  $query
     * @return Builder<Server>
     */
    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        if ($user->is_admin) {
            return $query;
        }

        return $query->whereHas('accessUsers', fn ($q) => $q->where('user_id', $user->id));
    }

    /**
     * Get permissions for a specific user on this server.
     * Returns null for owners (= all permissions), array for subusers.
     *
     * @return array<int, string>|null
     */
    public function permissionsForUser(User $user): ?array
    {
        $pivot = $this->accessUsers()->where('user_id', $user->id)->first();

        if (! $pivot) {
            return null;
        }

        if ($pivot->pivot->role === 'owner') {
            return null; // owner = all permissions
        }

        $perms = $pivot->pivot->permissions;

        return is_string($perms) ? json_decode($perms, true) : ($perms ?? []);
    }
}
