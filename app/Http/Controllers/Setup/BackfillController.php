<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Jobs\Setup\PelicanBackfillJob;
use App\Models\PelicanBackfillProgress;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Setup Wizard endpoints for the Pelican mirror bootstrap step. Triggers
 * the backfill job on demand and exposes its progress for polling.
 *
 * No CSRF / auth — the wizard is reachable only when PANEL_INSTALLED is
 * not set (EnsureInstalled middleware redirects elsewhere otherwise).
 */
class BackfillController extends Controller
{
    public function start(): JsonResponse
    {
        PelicanBackfillJob::dispatch();
        return response()->json(['started' => true]);
    }

    public function status(): JsonResponse
    {
        $rows = PelicanBackfillProgress::all()
            ->keyBy('resource_type')
            ->map(fn ($r) => [
                'processed' => $r->processed_count,
                'total' => $r->total_count,
                'completed' => $r->completed_at !== null,
                'started_at' => $r->started_at?->toIso8601String(),
                'completed_at' => $r->completed_at?->toIso8601String(),
                'last_error' => $r->last_error,
            ])
            ->all();

        $allDone = ! empty($rows) && collect($rows)->every(fn ($r) => $r['completed']);

        return response()->json([
            'resources' => $rows,
            'all_completed' => $allDone,
        ]);
    }

    /**
     * Generates a webhook bearer token, stores it encrypted, and returns
     * it ONCE so the wizard can show it to the admin. Subsequent calls
     * generate a new token (admin can re-roll if they didn't copy).
     */
    public function generateWebhookToken(): JsonResponse
    {
        $settings = app(SettingsService::class);
        $token = base64_encode(random_bytes(48));
        $settings->set('pelican_webhook_token', Crypt::encryptString($token));
        $settings->set('pelican_webhook_enabled', 'true');
        $settings->forget('bridge_pelican_webhook_token');

        return response()->json([
            'token' => $token,
            'endpoint' => url('/api/pelican/webhook'),
        ]);
    }

    public function heartbeat(): JsonResponse
    {
        $settings = app(SettingsService::class);
        $enabled = (string) $settings->get('pelican_webhook_enabled', 'false');
        $token = $settings->get('pelican_webhook_token');
        return response()->json([
            'enabled' => $enabled === 'true' || $enabled === '1',
            'token_configured' => ! empty($token),
        ]);
    }
}
