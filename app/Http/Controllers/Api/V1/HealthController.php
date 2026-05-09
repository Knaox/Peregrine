<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public health check. No auth required ; useful for shop integrators
 * wiring up their connectivity before minting an API key.
 */
class HealthController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'peregrine',
            'api_version' => 'v1',
            'time' => now()->toIso8601String(),
        ]);
    }
}
