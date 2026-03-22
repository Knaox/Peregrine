<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installed = config('panel.installed');

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
}
