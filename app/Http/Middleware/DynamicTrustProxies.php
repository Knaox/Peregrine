<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the static `$middleware->trustProxies(at: …)` from
 * bootstrap/app.php with a per-request lookup against the `settings`
 * table, so an admin can change the trusted-proxy list at /admin/settings
 * without restarting php-fpm or the Docker container.
 *
 * Falls back to the env value (then '*') when the settings table doesn't
 * exist yet (pre-install) or the row is missing — same behavior as the
 * previous static config.
 */
class DynamicTrustProxies
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $proxies = $this->resolveProxies();

        $request->setTrustedProxies(
            $proxies,
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    private function resolveProxies(): array
    {
        $raw = '*';
        try {
            if (Schema::hasTable('settings')) {
                $raw = (string) $this->settings->get('trusted_proxies', env('TRUSTED_PROXIES', '*'));
            } else {
                $raw = (string) env('TRUSTED_PROXIES', '*');
            }
        } catch (\Throwable) {
            $raw = (string) env('TRUSTED_PROXIES', '*');
        }

        if ($raw === '' || $raw === '*') {
            // Symfony spec : trust the connecting IP itself (= every request
            // host is treated as a trusted proxy). Same effect as the legacy
            // `'*'` shorthand in bootstrap/app.php.
            return ['*'];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), fn (string $v): bool => $v !== ''));
    }
}
