<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'email', 'password', 'locale', 'theme_mode', 'dashboard_layout', 'is_admin', 'pelican_user_id', 'stripe_customer_id', 'oauth_provider', 'oauth_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'locale' => 'string',
            'theme_mode' => 'string',
            'pelican_user_id' => 'integer',
            'is_admin' => 'boolean',
            'dashboard_layout' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    /**
     * Get the servers owned by this user (legacy — owner only).
     */
    public function servers(): HasMany
    {
        return $this->hasMany(Server::class);
    }

    /**
     * All servers this user can access (owner + subuser).
     */
    public function accessibleServers(): BelongsToMany
    {
        return $this->belongsToMany(Server::class, 'server_user')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    /**
     * Check a granular server permission. Owners implicitly have everything.
     * Returns the pivot role + permissions array for reuse in resources.
     *
     * @return array{role: string, permissions: array<int, string>}|null
     */
    public function serverAccess(Server $server): ?array
    {
        $pivot = DB::table('server_user')
            ->where('user_id', $this->id)
            ->where('server_id', $server->id)
            ->first();

        if (! $pivot) {
            return null;
        }

        $raw = $pivot->permissions;
        $permissions = is_string($raw) ? json_decode($raw, true) : ($raw ?? []);

        return [
            'role' => (string) $pivot->role,
            'permissions' => is_array($permissions) ? array_values($permissions) : [],
        ];
    }

    public function hasServerPermission(Server $server, string $permission): bool
    {
        $access = $this->serverAccess($server);

        if ($access === null) {
            return false;
        }

        if ($access['role'] === 'owner') {
            return true;
        }

        return in_array($permission, $access['permissions'], true);
    }
}
