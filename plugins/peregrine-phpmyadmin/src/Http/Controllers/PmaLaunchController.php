<?php

declare(strict_types=1);

namespace Plugins\PeregrinePhpmyadmin\Http\Controllers;

use App\Events\AdminActionPerformed;
use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Plugins\PeregrinePhpmyadmin\Models\PmaLaunchLog;
use Plugins\PeregrinePhpmyadmin\Services\PmaCredentialResolver;
use Plugins\PeregrinePhpmyadmin\Services\PmaTokenStore;
use Plugins\PeregrinePhpmyadmin\Settings\PmaSettings;

/**
 * SPA-facing launch endpoint. Resolves the database credentials, stashes them
 * behind a short-lived one-shot token, and returns the phpMyAdmin URL the
 * browser opens in a new tab. Gated by `database.view_password` — handing a
 * database to phpMyAdmin is equivalent to revealing its credentials.
 */
class PmaLaunchController extends Controller
{
    public function launch(Request $request, Server $server, string $database): JsonResponse
    {
        $settings = PmaSettings::make();
        abort_unless($settings->enabled && $settings->pmaUrl !== '', 403, 'phpMyAdmin integration is disabled.');

        $this->authorize('viewDatabasePassword', $server);

        $creds = app(PmaCredentialResolver::class)->resolve($server, $database);
        abort_if($creds === null, 404, 'Database not found.');

        PmaLaunchLog::create([
            'user_id' => $request->user()?->id,
            'server_id' => $server->id,
            'database_id' => $database,
            'event' => 'launch',
            'ip' => $request->ip(),
        ]);

        // Audit only when an admin acts on someone else's server (core scrubs secrets).
        AdminActionPerformed::dispatchIfCrossUser(
            admin: $request->user(),
            action: 'plugin.pma.launch',
            server: $server,
            payload: ['database' => $database],
            ip: $request->ip(),
            userAgent: (string) $request->userAgent(),
        );

        if ($settings->autoLogin) {
            // Auto-login: stash the credentials behind a one-shot token the
            // SignonScript redeems.
            $token = Str::random(64);
            app(PmaTokenStore::class)->put($token, $creds + ['user_id' => $request->user()?->id], $settings->tokenTtl);
            $url = $settings->pmaUrl.'/?signon_token='.$token;
            if ($settings->serverIndex > 0) {
                // Target the signon server so direct access keeps its normal login.
                $url .= '&server='.$settings->serverIndex;
            }
        } else {
            // Manual login: open phpMyAdmin's normal login (user types the
            // credentials, which they can reveal in the Databases tab).
            $url = $settings->pmaUrl.'/';
        }

        if ($settings->autoSelectDb && $creds['database'] !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?').'db='.urlencode($creds['database']);
        }

        return response()->json(['url' => $url]);
    }
}
