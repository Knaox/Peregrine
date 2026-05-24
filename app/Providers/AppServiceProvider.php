<?php

namespace App\Providers;

use App\Services\PluginManager;
use App\Services\SettingsService;
use Filament\Tables\Columns\Column;
use Illuminate\Auth\SessionGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
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
        $this->app->singleton(PluginManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Make EVERY admin table column hideable from the column manager. By
        // default Filament only lists columns explicitly marked toggleable();
        // this global default turns it on for all of them. A column can still
        // opt out with ->toggleable(false), and columns that set their own
        // ->toggleable(isToggledHiddenByDefault: true) keep that (the explicit
        // call in the table definition runs after this default and wins).
        Column::configureUsing(fn (Column $column) => $column->toggleable());

        // Apply DB-backed runtime overrides (debug + timezone) BEFORE any
        // service starts caching values. Saved in the `settings` table by
        // /admin/settings — they survive a Docker stack rebuild because
        // .env is wiped on container reset but the MySQL volume isn't.
        // Wrapped in try/catch because the boot can run before the table
        // exists (fresh install, migrations not yet ran).
        try {
            if (Schema::hasTable('settings')) {
                $settings = app(SettingsService::class);
                $debugSetting = $settings->get('app_debug', null);
                if ($debugSetting !== null) {
                    config(['app.debug' => $debugSetting === 'true']);
                }
                $tzSetting = (string) $settings->get('app_timezone', '');
                if ($tzSetting !== '' && in_array($tzSetting, \DateTimeZone::listIdentifiers(), true)) {
                    config(['app.timezone' => $tzSetting]);
                    date_default_timezone_set($tzSetting);
                }

                // "Remember me" cookie lifetime — DB-backed so it survives a
                // Docker redeploy and applies without a config:clear. Only the
                // HTTP `web` guard cares, so skip console/queue/Reverb where
                // there is no interactive login.
                if (! $this->app->runningInConsole()) {
                    $rememberDays = (int) $settings->get('auth_remember_lifetime_days', 30);
                    $guard = Auth::guard('web');
                    if ($rememberDays > 0 && $guard instanceof SessionGuard) {
                        $guard->setRememberDuration($rememberDays * 24 * 60);
                    }
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

        // /broadcasting/auth — Echo POSTs here once per private channel
        // subscription. A typical session opens 1–3 channels (server +
        // user + admin-mirror) ; an admin tab that lists 30 servers can
        // burst dozens of auths during a Reverb reconnect. The cap is
        // operator-tunable via /admin/settings → Network so a fleet of
        // many concurrent admins behind a single Cloudflare-proxied IP
        // can lift it without an env redeploy. Floor at 30/min so the
        // operator can't accidentally lock themselves out.
        RateLimiter::for('broadcasting-auth', function (Request $request) {
            $perMinute = self::resolveBroadcastingAuthLimit();

            // Per-user when authenticated (the auth route requires a
            // session anyway), per-IP otherwise so an unauth retry
            // storm from a single browser doesn't escape the bucket.
            return Limit::perMinute(max($perMinute, 30))
                ->by($request->user()?->id ?: $request->ip());
        });

        // Pelican Client-API proxy endpoints (websocket creds, server
        // resources). Default 6000/min/user (≈ 100/sec) — sized to never
        // fire in practice for legitimate use even with rapid F5 spam in
        // dev (React StrictMode double-mounts every effect, doubling the
        // request count). The cap exists as a safety net for a runaway
        // retry storm (broken hook, infinite re-render) so a single bad
        // tab can't saturate Pelican's upstream API.
        //
        // For context : `useServerResources` polls /resources every 5 s
        // (12/min/server) ; `useWingsWebSocket` fetches /websocket on
        // every (re)connect ; React StrictMode multiplies dev-mode
        // counts by ~2. A page displaying 5 servers refreshed 10 times
        // in a minute = ~120 hits — well within 6000.
        //
        // Operators can dial it any direction via /admin/settings →
        // Network. Floor 60/min stays in case of a typo so the panel
        // remains usable.
        RateLimiter::for('pelican-proxy', function (Request $request) {
            $perMinute = self::resolvePelicanProxyLimit();

            return Limit::perMinute(max($perMinute, 60))
                ->by($request->user()?->id ?: $request->ip());
        });

        // Manual broadcasting route registration : matches Laravel's
        // default `Broadcast::routes()` shape (GET/POST /broadcasting/auth)
        // but layers our `throttle:broadcasting-auth` middleware on top of
        // the default `web` group. We removed `channels:` from
        // `withRouting()` (bootstrap/app.php) precisely so this manual
        // registration is the single source of truth — Laravel's
        // auto-register would otherwise add a second un-throttled route.
        Broadcast::routes([
            'middleware' => ['web', 'throttle:broadcasting-auth'],
        ]);
        // `channels.php` itself only contains `Broadcast::channel(...)`
        // authorization callbacks, no routes. Loading it here matches
        // what `withRouting(channels: ...)` would have done.
        require __DIR__.'/../../routes/channels.php';

        // Boot active plugins
        if (config('panel.installed')) {
            app(PluginManager::class)->bootPlugins();
        }
    }

    /**
     * Resolve the configured /broadcasting/auth rate cap from the
     * `settings` table. Falls back to 240 (≈ 4 / second) when the
     * setting was never written, the table doesn't exist yet (fresh
     * install pre-migration), or the stored value isn't numeric.
     *
     * Wrapped in try/catch because RateLimiter::for closures fire on
     * EVERY auth request — a missing settings table (very early boot,
     * setup wizard) must NOT break broadcasting auth, otherwise the
     * panel's setup screen itself would 500 trying to live-update.
     */
    private static function resolveBroadcastingAuthLimit(): int
    {
        try {
            $value = app(SettingsService::class)
                ->get('broadcasting_auth_rate_limit_per_minute', 240);
        } catch (\Throwable) {
            return 240;
        }

        return is_numeric($value) ? (int) $value : 240;
    }

    /**
     * Resolve the configured Peregrine-side cap on Pelican Client-API
     * proxy endpoints (websocket / resources / files / etc.) from the
     * `settings` table. Same defensive try/catch as the broadcasting
     * cap — never let a missing settings table throw out of a route
     * middleware closure.
     */
    private static function resolvePelicanProxyLimit(): int
    {
        try {
            $value = app(SettingsService::class)
                ->get('pelican_proxy_rate_limit_per_minute', 6000);
        } catch (\Throwable) {
            return 6000;
        }

        return is_numeric($value) ? (int) $value : 6000;
    }
}
