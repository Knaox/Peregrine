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
        $middleware->statefulApi();
        // Trusted proxies: comma-separated list of IPs/CIDRs the app should
        // honor X-Forwarded-* from. Defaults to '*' (trust everything) so the
        // bundled docker-compose stacks work out of the box behind any reverse
        // proxy. Operators running on a known proxy network (NPM, Traefik,
        // Cloudflare tunnel, etc.) should narrow this down — see .env.example.
        $trustedProxies = env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies(
            at: $trustedProxies === '*'
                ? '*'
                : array_values(array_filter(array_map('trim', explode(',', (string) $trustedProxies)))),
        );
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
