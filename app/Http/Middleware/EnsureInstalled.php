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

        if (!$installed && !$request->is('setup', 'setup/*', 'api/setup/*')) {
            return redirect('/setup');
        }

        if ($installed && $request->is('setup', 'setup/*')) {
            return redirect('/');
        }

        return $next($request);
    }
}
