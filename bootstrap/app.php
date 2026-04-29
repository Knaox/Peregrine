<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

/*
 * Register plugin autoloaders before Laravel boots.
 * This ensures queue workers can deserialize plugin classes.
 */
require __DIR__ . '/../plugins/autoload.php';

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\EnsureInstalled::class);
        // Read users.locale (or the panel-wide default_locale) into
        // app()->setLocale() so every __() in Filament/Notifications/Mailables
        // resolves against the right language file.
        $middleware->append(\App\Http\Middleware\SetUserLocale::class);
        $middleware->statefulApi();
        // Trusted proxies are now read per-request from the `settings`
        // table by DynamicTrustProxies, so admins can change the list at
        // /admin/settings without a container restart. The middleware
        // falls back to env('TRUSTED_PROXIES', '*') when the table isn't
        // ready yet (fresh install).
        $middleware->prepend(\App\Http\Middleware\DynamicTrustProxies::class);
        // The 2FA challenge endpoint is unauthenticated and protected by a
        // single-use opaque challenge_id stored in Redis (5 min TTL) — CSRF
        // would be redundant and causes 419s because the password-login path
        // regenerates the session token between /login and the challenge POST.
        $middleware->validateCsrfTokens(except: [
            'api/auth/2fa/challenge',
        ]);
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'two-factor' => \App\Http\Middleware\RequireTwoFactor::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
