<?php

use App\Http\Controllers\Setup\SetupController;
use Illuminate\Support\Facades\Route;

Route::prefix('setup')->group(function () {
    Route::post('test-database', [SetupController::class, 'testDatabase']);
    Route::post('test-pelican', [SetupController::class, 'testPelican']);
    Route::post('install', [SetupController::class, 'install']);
    Route::get('docker-detect', [SetupController::class, 'dockerDetect']);
});
