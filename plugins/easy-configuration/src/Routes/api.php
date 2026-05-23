<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Plugins\EasyConfiguration\Http\Controllers\Admin\EggCatalogController;
use Plugins\EasyConfiguration\Http\Controllers\Admin\ImportConfigController;
use Plugins\EasyConfiguration\Http\Controllers\Admin\ServerCatalogController;
use Plugins\EasyConfiguration\Http\Controllers\Admin\ServerFileBrowserController;
use Plugins\EasyConfiguration\Http\Controllers\Admin\TemplateController;
use Plugins\EasyConfiguration\Http\Controllers\BoostController;
use Plugins\EasyConfiguration\Http\Controllers\CopyController;
use Plugins\EasyConfiguration\Http\Controllers\ServerConfigController;
use Plugins\EasyConfiguration\Http\Middleware\EnsureAdmin;

/**
 * Routes mounted under `/api/plugins/easy-configuration` by the ServiceProvider.
 * `{server}` is the numeric server id (the React shell routes by server.id).
 * Group `throttle:240,1` keeps live editing (read on open, save, status polling)
 * clear of the api default cap.
 */
Route::middleware(['auth', 'throttle:240,1'])->group(function (): void {
    // Player-facing server config + power (permission-gated in the controller).
    Route::get('servers/{server}/config', [ServerConfigController::class, 'show']);
    Route::put('servers/{server}/config', [ServerConfigController::class, 'update']);
    Route::get('servers/{server}/status', [ServerConfigController::class, 'status']);
    Route::post('servers/{server}/power', [ServerConfigController::class, 'power']);

    // Copy configuration to other servers of the same egg.
    Route::get('servers/{server}/copy/targets', [CopyController::class, 'targets']);
    Route::post('servers/{server}/copy', [CopyController::class, 'store']);
    Route::get('servers/{server}/copy/log', [CopyController::class, 'log']);

    // Boost scheduling.
    Route::get('servers/{server}/boosts', [BoostController::class, 'index']);
    Route::post('servers/{server}/boosts', [BoostController::class, 'store']);
    Route::put('servers/{server}/boosts/{boost}', [BoostController::class, 'update']);
    Route::get('servers/{server}/boosts/history', [BoostController::class, 'history']);
    Route::delete('servers/{server}/boosts/{boost}', [BoostController::class, 'destroy']);

    // Admin template management (is_admin enforced server-side).
    Route::middleware(EnsureAdmin::class)->prefix('admin')->group(function (): void {
        Route::get('templates', [TemplateController::class, 'index']);
        Route::post('templates', [TemplateController::class, 'store']);
        Route::post('templates/import', [TemplateController::class, 'import']);
        // Static segment BEFORE {id} so it isn't captured as a template id.
        Route::get('templates/example', [TemplateController::class, 'example']);
        Route::get('templates/{id}', [TemplateController::class, 'show']);
        Route::put('templates/{id}', [TemplateController::class, 'update']);
        Route::post('templates/{id}/parameters', [TemplateController::class, 'addParameter']);
        Route::delete('templates/{id}', [TemplateController::class, 'destroy']);
        Route::get('templates/{id}/export', [TemplateController::class, 'export']);
        Route::get('eggs', [EggCatalogController::class, 'index']);

        // Import a real config file from a server to scaffold a template block.
        Route::get('servers', [ServerCatalogController::class, 'index']);
        Route::get('servers/{server}/files', [ServerFileBrowserController::class, 'index']);
        Route::get('servers/{server}/env-vars', [ServerCatalogController::class, 'envVars']);
        Route::post('import-config', ImportConfigController::class);
    });
});
