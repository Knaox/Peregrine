<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Plugins\PeregrinePhpmyadmin\Settings\PmaSettings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the public redeem endpoint with the configured shared secret, sent by
 * the SignonScript in the `X-Plugin-Secret` header. Constant-time comparison
 * (`hash_equals`) avoids timing attacks. Fails closed: an unconfigured secret
 * denies every request.
 */
class EnsurePmaSharedSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = PmaSettings::make()->sharedSecret;
        $provided = (string) $request->header('X-Plugin-Secret', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            abort(403, 'Invalid shared secret.');
        }

        return $next($request);
    }
}
