<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks admin routes when admin 2FA enforcement is on and the current admin
 * hasn't set up 2FA. Plan §S5 / Étape B.
 *
 * Non-admins pass through unconditionally (the `admin` middleware handles
 * their 403 elsewhere). Non-authenticated requests pass through — `auth`
 * middleware deals with them.
 */
class RequireTwoFactor
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_admin) {
            return $next($request);
        }

        $required = $this->settings->get('auth_2fa_required_admins', 'false') === 'true';

        if ($required && ! $user->hasTwoFactor()) {
            return new JsonResponse([
                'error' => 'auth.2fa.required_admin_setup',
                'setup_url' => '/settings/security',
            ], 403);
        }

        return $next($request);
    }
}
