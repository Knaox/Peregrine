<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\PeregrinePhpmyadmin\Models\PmaLaunchLog;
use Plugins\PeregrinePhpmyadmin\Services\PmaTokenStore;

/**
 * Public, server-to-server endpoint hit by phpMyAdmin's SignonScript. The
 * shared-secret + IP-allowlist middlewares guard it; here we only exchange a
 * valid one-shot token for the credentials, then delete it (handled atomically
 * by the token store). An invalid/expired/replayed token returns 404.
 */
class PmaRedeemController extends Controller
{
    public function redeem(Request $request): JsonResponse
    {
        $payload = app(PmaTokenStore::class)->pull((string) $request->input('token', ''));
        abort_if($payload === null, 404, 'Invalid or expired token.');

        PmaLaunchLog::create([
            'user_id' => $payload['user_id'] ?? null,
            'server_id' => null,
            'database_id' => (string) ($payload['database'] ?? ''),
            'event' => 'redeem',
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'username' => $payload['username'] ?? '',
            'password' => $payload['password'] ?? '',
            'host' => $payload['host'] ?? '',
            'port' => $payload['port'] ?? 3306,
            'database' => $payload['database'] ?? '',
        ]);
    }
}
