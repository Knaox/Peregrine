<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/**
 * Routes are mounted under `/api/plugins/easy-configuration` by the plugin's
 * ServiceProvider. Server lookup uses the public `identifier` (matching the
 * rest of the panel's plugin contract — see invitations / modpack-installer).
 *
 * The full surface (server config read/write, copy, boosts, admin template
 * CRUD) is filled in across later phases. The `health` probe exists from the
 * scaffold so plugin activation can be smoke-tested end to end.
 *
 * Group-level `throttle:240,1` lifts the per-user-per-minute cap from the api
 * default so a player live-editing a long config file doesn't trip 429.
 */
Route::middleware(['auth', 'throttle:240,1'])->group(function (): void {
    Route::get('health', static fn (): JsonResponse => response()->json(['data' => ['ok' => true]]));
});
