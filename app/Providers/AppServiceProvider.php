<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
        // Apply DB-backed runtime overrides (debug + timezone) BEFORE any
        // service starts caching values. Saved in the `settings` table by
        // /admin/settings — they survive a Docker stack rebuild because
        // .env is wiped on container reset but the MySQL volume isn't.
        // Wrapped in try/catch because the boot can run before the table
        // exists (fresh install, migrations not yet ran).
        try {
            if (Schema::hasTable('settings')) {
                $settings = app(\App\Services\SettingsService::class);
                $debugSetting = $settings->get('app_debug', null);
                if ($debugSetting !== null) {
                    config(['app.debug' => $debugSetting === 'true']);
                }
                $tzSetting = (string) $settings->get('app_timezone', '');
                if ($tzSetting !== '' && in_array($tzSetting, \DateTimeZone::listIdentifiers(), true)) {
                    config(['app.timezone' => $tzSetting]);
                    date_default_timezone_set($tzSetting);
                }
            }
        } catch (\Throwable) {
            // Pre-install / DB unreachable — fall back silently to env values.
        }

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

        // Bridge API — accepts plan upserts from the Shop. Per-IP throttling
        // (the Shop is a single source, so IP-based limit is sufficient).
        RateLimiter::for('bridge', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Stripe webhook — Stripe has documented fixed IPs and can spike
        // during dunning runs (paying invoices for many subscribers in a
        // short window). Higher cap, still per-IP for safety.
        RateLimiter::for('stripe-webhook', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        // Pelican outgoing webhook (Bridge Paymenter mode). Pelican panel is
        // typically a single source per Peregrine install, but bursts can
        // happen on initial setup (admin re-emits all events when wiring up
        // the webhook). Tolerant cap to avoid drops during seeding.
        RateLimiter::for('pelican-webhook', function (Request $request) {
            return Limit::perMinute(240)->by($request->ip());
        });

        // Boot active plugins
        if (config('panel.installed')) {
            app(\App\Services\PluginManager::class)->bootPlugins();
        }
    }
}
