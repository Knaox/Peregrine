<?php

namespace Plugins\Invitations;

use App\Events\Bridge\SubuserSynced;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Plugins\Invitations\Listeners\SyncPelicanSubuser;
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

        // Register plugin routes (authenticated management + public invitation landing)
        Route::prefix('api/plugins/invitations')
            ->middleware('api')
            ->group(__DIR__ . '/Routes/api.php');

        Route::prefix('api/plugins/invitations')
            ->middleware('api')
            ->group(__DIR__ . '/Routes/public.php');

        // Register mail views
        $this->loadViewsFrom(__DIR__ . '/../views', 'plugins.invitations');

        // Subscribe to core's SubuserSynced event so we can mirror Pelican
        // subuser changes into our local invitations_pelican_subusers table.
        // SYNC listener (no queue) per plugin queue-safe contract.
        Event::listen(SubuserSynced::class, [SyncPelicanSubuser::class, 'handle']);
    }
}
