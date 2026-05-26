<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Plugins\PeregrinePlayerCounter\Http\Controllers\PlayerCountController;

/**
 * Mounted under `/api/plugins/peregrine-player-counter` by the ServiceProvider.
 * `{server}` is the numeric server id (the React shell routes by server.id).
 */
Route::middleware(['auth', 'throttle:60,1'])->group(function (): void {
    Route::get('servers/{server}/players', [PlayerCountController::class, 'show']);
});

// Destructive one-click RCON setup (allocates a port + restarts the server).
// Strict throttle; the controller gates on the createAllocation ability and the
// SPA shows a confirmation before calling it.
Route::middleware(['auth', 'throttle:5,1'])->group(function (): void {
    Route::post('servers/{server}/resolve-rcon', [PlayerCountController::class, 'resolveRcon']);
});
