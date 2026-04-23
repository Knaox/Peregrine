<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate as FilamentAuthenticate;
use Illuminate\Http\Request;

/**
 * Auth middleware for the Filament admin panel that redirects unauthenticated
 * visitors to the React SPA's `/login` page instead of Filament's built-in
 * `/admin/login`.
 *
 * Why : we want a single source of truth for sign-in (multi-provider auth,
 * 2FA, OAuth canonical IdP redirect, ...) — the React login page wires all
 * of that. The Filament login page would let admins bypass the OAuth
 * canonical IdP guard, so we disable it entirely (`->login()` is removed
 * from `AdminPanelProvider`) and route every unauthenticated /admin/* hit
 * through this middleware to land on /login with the original URL preserved
 * via `?redirect_to=`.
 */
class RedirectAdminToFrontendLogin extends FilamentAuthenticate
{
    /**
     * @param  Request  $request
     */
    protected function redirectTo($request): string
    {
        $intended = $request->fullUrl();

        return route('frontend.login') . '?redirect_to=' . urlencode($intended);
    }
}
