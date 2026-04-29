<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the runtime app locale on every request:
 *   1. authenticated user.locale (en/fr) — top priority
 *   2. fall back to the panel-wide `default_locale` setting
 *   3. fall back to config('app.locale')
 *
 * Without this middleware the admin panel renders every label against
 * config('app.locale') (en) regardless of the admin's preference, so __()
 * calls in Filament Resources/Pages never resolve to French.
 */
class SetUserLocale
{
    private const SUPPORTED = ['en', 'fr'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $candidate = $user?->locale
            ?? $this->settingDefault()
            ?? config('app.locale', 'en');

        $locale = in_array($candidate, self::SUPPORTED, true)
            ? (string) $candidate
            : 'en';

        app()->setLocale($locale);

        return $next($request);
    }

    private function settingDefault(): ?string
    {
        try {
            return app(\App\Services\SettingsService::class)
                ->get('default_locale', null);
        } catch (\Throwable) {
            return null;
        }
    }
}
