<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\SettingsService;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
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

#[Fillable(['name', 'email', 'password', 'locale', 'theme_mode', 'dashboard_layout', 'is_admin', 'pelican_user_id', 'stripe_customer_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery, Notifiable;

    /**
     * Cast additions for app_authentication_* are merged by the Filament
     * InteractsWithApp* traits at init time — don't duplicate them here.
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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_admin) {
            return false;
        }

        if (app(SettingsService::class)->get('auth_2fa_required_admins', 'false') === 'true'
            && ! $this->hasTwoFactor()
        ) {
            return false;
        }

        return true;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function oauthIdentities(): HasMany
    {
        return $this->hasMany(OAuthIdentity::class);
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
        // Admin bypass — aligned with the scoped Gate::before in
        // AuthServiceProvider. Applies ONLY to server-scoped permissions,
        // same whitelist as the Gate (plan §S5).
        if ($this->is_admin) {
            return true;
        }

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
