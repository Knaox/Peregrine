<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\PluginManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS when behind a reverse proxy or when APP_URL is https
        if (str_starts_with(config('app.url', ''), 'https')) {
            URL::forceScheme('https');
        }

        // NOTE: the previous global `Gate::before(... return true ...)` for
        // admins moved to AuthServiceProvider with a scoped model whitelist
        // (plan §S5). Never reintroduce an unscoped bypass here.

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('server-actions', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Auth hardening — plan §S4.
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('2fa-challenge', function (Request $request) {
            return Limit::perMinute(5)->by((string) ($request->input('challenge_id') ?? $request->ip()));
        });

        RateLimiter::for('2fa-setup', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('social-redirect', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Boot active plugins
        if (config('panel.installed')) {
            app(\App\Services\PluginManager::class)->bootPlugins();
        }
    }
}
