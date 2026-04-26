<?php

use Illuminate\Support\Facades\Route;
use Plugins\EggConfigEditor\Http\Controllers\ConfigEditorController;

Route::middleware('auth')->group(function () {
    Route::get('servers/{serverId}/configs', [ConfigEditorController::class, 'listConfigs']);
    Route::get('servers/{serverId}/configs/{configId}', [ConfigEditorController::class, 'readConfig']);
    Route::post('servers/{serverId}/configs/{configId}', [ConfigEditorController::class, 'saveConfig']);
});
