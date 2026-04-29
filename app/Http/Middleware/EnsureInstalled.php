<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    /**
     * Sentinel file used to mark "the wizard is mid-finishing-flow".
     * Written by SetupController::install() the moment the install
     * succeeds and removed when the admin clicks Finish on WebhookStep.
     * While present, /setup remains reachable so the SPA can run steps
     * 7 (Backfill) and 8 (Webhook) — without it, EnsureInstalled would
     * redirect /setup to / the moment a refresh happens (or any asset
     * triggers a navigation), kicking the admin out of the wizard.
     *
     * 1-hour staleness cap : if the file is older, we ignore it. Covers
     * the case where the admin abandoned the wizard mid-flow and comes
     * back days later — they should land on /, not be sent back to a
     * stale wizard state.
     */
    private const FINISHING_SENTINEL = '.wizard_finishing';

    private const FINISHING_MAX_AGE_SECONDS = 3600;

    public function handle(Request $request, Closure $next): Response
    {
        $installed = config('panel.installed') || $this->installedMarkerExists();

        if (!$installed && !$request->is('setup', 'setup/*', 'api/setup/*', 'livewire*', 'filament*', 'docs/*')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'panel_not_installed'], 503);
            }
            return redirect('/setup');
        }

        // Once installed, /setup should normally redirect to / (no point
        // showing the wizard again). EXCEPTION : when the admin is in the
        // middle of finishing the wizard (steps 7/8 still pending) we let
        // them through. The wizard SPA itself drops a sentinel at the
        // moment install succeeds and removes it on Finish.
        if ($installed && $request->is('setup', 'setup/*') && ! $this->isWizardFinishing()) {
            return redirect('/');
        }

        return $next($request);
    }

    /**
     * Fallback check for "installed" that doesn't rely on env().
     *
     * phpdotenv runs in immutable mode: once a php-fpm worker has loaded
     * PANEL_INSTALLED=false at boot, writing the .env mid-process doesn't
     * update the value for that worker. The Setup Wizard drops a sentinel
     * file on success — its presence is the source of truth until the
     * workers cycle and re-read the .env.
     */
    private function installedMarkerExists(): bool
    {
        return file_exists(storage_path('.installed'));
    }

    /**
     * Is a wizard finish-flow in progress ? Returns true while the
     * `.wizard_finishing` sentinel exists AND is fresh (less than an hour
     * old). The sentinel is touched on install success and removed by the
     * Finish button on WebhookStep ; the staleness cap saves us if the
     * Finish click never lands.
     */
    private function isWizardFinishing(): bool
    {
        $sentinel = storage_path(self::FINISHING_SENTINEL);
        if (! file_exists($sentinel)) {
            return false;
        }
        $age = time() - filemtime($sentinel);
        if ($age > self::FINISHING_MAX_AGE_SECONDS) {
            // Stale — clean up so we don't keep the wizard reachable
            // forever after a long-abandoned install.
            @unlink($sentinel);
            return false;
        }
        return true;
    }
}
