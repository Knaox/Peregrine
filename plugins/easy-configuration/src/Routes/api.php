<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Plugins\EasyConfiguration\Http\Controllers\Admin\EggCatalogController;
use Plugins\EasyConfiguration\Http\Controllers\Admin\TemplateController;
use Plugins\EasyConfiguration\Http\Controllers\ServerConfigController;
use Plugins\EasyConfiguration\Http\Middleware\EnsureAdmin;

/**
 * Routes mounted under `/api/plugins/easy-configuration` by the ServiceProvider.
 * Server lookup uses the public `identifier`. Group `throttle:240,1` keeps live
 * editing (read on open, save, status polling) clear of the api default cap.
 */
Route::middleware(['auth', 'throttle:240,1'])->group(function (): void {
    // Player-facing server config + power (permission-gated in the controller).
    Route::get('servers/{server}/config', [ServerConfigController::class, 'show']);
    Route::put('servers/{server}/config', [ServerConfigController::class, 'update']);
    Route::get('servers/{server}/status', [ServerConfigController::class, 'status']);
    Route::post('servers/{server}/power', [ServerConfigController::class, 'power']);

    // Admin template management (is_admin enforced server-side).
    Route::middleware(EnsureAdmin::class)->prefix('admin')->group(function (): void {
        Route::get('templates', [TemplateController::class, 'index']);
        Route::post('templates', [TemplateController::class, 'store']);
        Route::post('templates/import', [TemplateController::class, 'import']);
        Route::get('templates/{id}', [TemplateController::class, 'show']);
        Route::put('templates/{id}', [TemplateController::class, 'update']);
        Route::delete('templates/{id}', [TemplateController::class, 'destroy']);
        Route::get('templates/{id}/export', [TemplateController::class, 'export']);
        Route::get('eggs', [EggCatalogController::class, 'index']);
    });
});
