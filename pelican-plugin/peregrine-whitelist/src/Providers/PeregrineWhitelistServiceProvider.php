<?php

namespace Pelican\PeregrineWhitelist\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\IpUtils;

class PeregrineWhitelistServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            plugin_path('peregrine-whitelist', 'config/peregrine-whitelist.php'),
            'peregrine-whitelist'
        );
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(
            plugin_path('peregrine-whitelist', 'lang'),
            'peregrine-whitelist'
        );

        // Re-register the API limiters AFTER all providers have booted, so this
        // definitively overrides RouteServiceProvider::configureRateLimiting()
        // regardless of provider order. `RateLimiter::for` with an existing name
        // overwrites, and `booted` callbacks run last.
        $this->app->booted(function () {
            $this->overrideApiRateLimiters();
        });
    }

    /**
     * For each API limiter: trusted Peregrine sources get Limit::none()
     * (unlimited — Peregrine self-throttles per user); everyone else keeps
     * Pelican's normal per-user/IP limit from config/http.php.
     */
    protected function overrideApiRateLimiters(): void
    {
        foreach (['client', 'application'] as $type) {
            RateLimiter::for("api.{$type}", function (Request $request) use ($type) {
                if ($this->isTrusted((string) $request->ip())) {
                    return Limit::none();
                }

                $key = $request->user()?->uuid ?: $request->ip();

                return Limit::perMinutes(
                    config("http.rate_limit.{$type}_period"),
                    config("http.rate_limit.{$type}")
                )->by($key);
            });
        }
    }

    protected function isTrusted(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        $trusted = $this->trustedIps();

        return $trusted !== [] && IpUtils::checkIp($ip, $trusted);
    }

    /**
     * Effective trusted IP/CIDR list (config IPs + resolved hostnames),
     * memoised per process to avoid DNS lookups on every request.
     *
     * @return string[]
     */
    protected function trustedIps(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $ips = (array) config('peregrine-whitelist.ips', []);

        foreach ((array) config('peregrine-whitelist.hostnames', []) as $host) {
            $resolved = get_ip_from_hostname($host);
            if (is_string($resolved) && $resolved !== '') {
                $ips[] = $resolved;
            }
        }

        return $cache = array_values(array_unique(array_filter($ips)));
    }
}
