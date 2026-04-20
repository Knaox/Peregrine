<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = config('panel.installed') || $this->installedMarkerExists();

        if (!$installed && !$request->is('setup', 'setup/*', 'api/setup/*', 'livewire*', 'filament*', 'docs/*')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'panel_not_installed'], 503);
            }
            return redirect('/setup');
        }

        if ($installed && $request->is('setup', 'setup/*')) {
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
}
