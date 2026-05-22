<?php

declare(strict_types=1);

namespace Plugins\EasyConfiguration\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate template-management endpoints to admins. Template editing is an
 * operator capability (not a per-server subuser permission), so the security
 * boundary is `User::is_admin` enforced server-side here — the React admin page
 * adds an in-page guard for UX only.
 */
final class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! $user->is_admin) {
            abort(403);
        }

        return $next($request);
    }
}
