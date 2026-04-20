<?php

namespace App\Models;

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
        'name',
        'status',
        'egg_id',
        'plan_id',
        'stripe_subscription_id',
        'payment_intent_id',
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
            'plan_id' => 'integer',
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
     * Get the plan associated with this server.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ServerPlan::class, 'plan_id');
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
