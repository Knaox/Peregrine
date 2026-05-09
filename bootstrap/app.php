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
        // Phase 5 — public API v1 surface for third-party shops.
        // Loaded into the `api` middleware group (no CSRF, JSON-friendly)
        // and prefixed `/api/v1`. Per-route auth via EnsureShopApiKey.
        then: fn () => \Illuminate\Support\Facades\Route::middleware('api')
            ->prefix('api/v1')
            ->name('api.v1.')
            ->group(__DIR__.'/../routes/api_v1.php'),
        // `channels:` intentionally NOT passed here — Laravel's
        // auto-registration would attach `/broadcasting/auth` with
        // bare `web` middleware (no throttle). We register the
        // route + load `channels.php` manually in
        // `AppServiceProvider::boot()` so we can attach a
        // configurable `throttle:broadcasting-auth` middleware
        // (cap stored in settings, default 240/min). Without that,
        // an Echo reconnect fanning out across N private channels
        // can spam /broadcasting/auth and trigger 429s upstream
        // (Cloudflare, reverse proxy, …) which silently bricks
        // every live update on the panel.
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
        //
        // Use replace() (not prepend) so DynamicTrustProxies sits in
        // Laravel's TrustProxies slot. A bare prepend leaves the framework
        // TrustProxies in the stack — it runs after ours and resets the
        // trusted-proxies array to [] when $middleware->trustProxies() was
        // never called, which silently breaks isSecure() / signed URLs
        // behind reverse proxies (NPM, Cloudflare).
        $middleware->replace(
            \Illuminate\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\DynamicTrustProxies::class,
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
            'shop_api_key' => \App\Http\Middleware\EnsureShopApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
