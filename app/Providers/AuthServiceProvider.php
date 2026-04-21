<?php

namespace App\Providers;

use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Admin bypass — strictly scoped whitelist. Plan §S5.
     *
     * Extending this list demands a dedicated security review. DO NOT add any
     * model carrying billing, payment, or personally sensitive data (sessions,
     * API tokens, 2FA secrets) — an admin has legitimate operational need on
     * servers; they have NO such need on another user's wallet.
     *
     * Current whitelist:
     *   - App\Models\Server — operate on any user's server (power / files / etc.)
     *
     * Returning `null` (not `false`) lets the policy chain continue normally
     * for non-whitelisted abilities; `true` grants unconditional access.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! $user->is_admin) {
                return null;
            }

            $resource = $arguments[0] ?? null;

            if ($resource instanceof Server) {
                return true;
            }

            return null;
        });
    }
}
