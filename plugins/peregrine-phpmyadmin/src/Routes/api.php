<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Plugins\PeregrinePhpmyadmin\Http\Controllers\PmaLaunchController;
use Plugins\PeregrinePhpmyadmin\Http\Controllers\PmaRedeemController;
use Plugins\PeregrinePhpmyadmin\Http\Controllers\PmaStateController;
use Plugins\PeregrinePhpmyadmin\Http\Middleware\EnsurePmaIpAllowlist;
use Plugins\PeregrinePhpmyadmin\Http\Middleware\EnsurePmaSharedSecret;

/**
 * Mounted under `/api/plugins/peregrine-phpmyadmin` by the ServiceProvider.
 * `{server}` is the numeric server id (the React shell routes by server.id);
 * `{database}` is Pelican's database string id.
 */
Route::middleware(['auth', 'throttle:30,1'])->group(function (): void {
    Route::get('state', PmaStateController::class);
    Route::post('servers/{server}/databases/{database}/launch', [PmaLaunchController::class, 'launch']);
});

// Public — called server-to-server by phpMyAdmin's SignonScript.
Route::post('redeem', [PmaRedeemController::class, 'redeem'])
    ->middleware([EnsurePmaSharedSecret::class, EnsurePmaIpAllowlist::class]);
