<?php

namespace Plugins\Invitations;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\Invitations\Services\PermissionRegistry;

class InvitationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register permissions as static singleton (container bindings don't persist for dynamic plugins)
        PermissionRegistry::getInstance()->registerPelicanDefaults();

        // Rate limiter for the resend-invitation endpoint. 5 / min / user
        // is generous enough that an operator legitimately re-sending one
        // invitation a few times never hits it, but a runaway frontend
        // bug or a bored admin spam-clicking the button can't flood the
        // recipient's mailbox. Keyed per-user so multiple admins on the
        // same panel each get their own bucket.
        RateLimiter::for('invitation-resend', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // Register plugin routes (authenticated management + public invitation landing)
        Route::prefix('api/plugins/invitations')
            ->middleware('api')
            ->group(__DIR__ . '/Routes/api.php');

        Route::prefix('api/plugins/invitations')
            ->middleware('api')
            ->group(__DIR__ . '/Routes/public.php');

        // Register mail views
        $this->loadViewsFrom(__DIR__ . '/../views', 'plugins.invitations');
    }
}
