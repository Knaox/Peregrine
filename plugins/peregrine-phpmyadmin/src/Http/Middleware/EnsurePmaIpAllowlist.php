<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Plugins\PeregrinePhpmyadmin\Settings\PmaSettings;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional second gate on the public redeem endpoint: when the admin has
 * configured an IP/CIDR allowlist, only the phpMyAdmin host(s) may redeem
 * tokens. An empty allowlist means no restriction.
 */
class EnsurePmaIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = PmaSettings::make()->ipAllowlist;

        if ($allowlist !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowlist)) {
            abort(403, 'IP not allowed.');
        }

        return $next($request);
    }
}
