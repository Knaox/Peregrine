<?php

use Illuminate\Support\Facades\Route;
use Plugins\EggConfigEditor\Http\Controllers\ConfigEditorController;

Route::middleware('auth')->group(function () {
    Route::get('servers/{serverId}/configs', [ConfigEditorController::class, 'listConfigs']);
    Route::get('servers/{serverId}/configs/{configId}', [ConfigEditorController::class, 'readConfig']);
    Route::post('servers/{serverId}/configs/{configId}', [ConfigEditorController::class, 'saveConfig']);
    // Toggle "this key is not actually a boolean" — flips the override flag
    // for this config_key on this EggConfigFile row and persists across
    // sessions for every server using this egg.
    Route::post('servers/{serverId}/configs/{configId}/non-boolean-keys/toggle', [ConfigEditorController::class, 'toggleNonBooleanKey']);
});
