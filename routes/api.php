<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OAuthController;
use App\Http\Controllers\Api\ServerConsoleController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\ServerFileController;
use App\Http\Controllers\Api\ServerPowerController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Setup\SetupController;
use Illuminate\Support\Facades\Route;

Route::prefix('setup')->group(function () {
    Route::post('test-database', [SetupController::class, 'testDatabase']);
    Route::post('test-pelican', [SetupController::class, 'testPelican']);
    Route::post('install', [SetupController::class, 'install']);
    Route::get('docker-detect', [SetupController::class, 'dockerDetect']);
});

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);
});

// OAuth routes
Route::prefix('oauth')->group(function () {
    Route::get('redirect', [OAuthController::class, 'redirect']);
    Route::get('callback', [OAuthController::class, 'callback']);
});

// Settings (public)
Route::prefix('settings')->group(function () {
    Route::get('branding', [SettingsController::class, 'branding']);
    Route::get('auth-mode', [SettingsController::class, 'authMode']);
    Route::get('theme', [SettingsController::class, 'theme']);
});

// Protected API routes
Route::middleware('auth')->group(function () {
    // Servers
    Route::get('servers', [ServerController::class, 'index']);
    Route::get('servers/stats', [ServerController::class, 'batchStats']);
    Route::get('servers/{server}', [ServerController::class, 'show']);

    // Server power (rate limited)
    Route::middleware('throttle:server-actions')->group(function () {
        Route::post('servers/{server}/power', ServerPowerController::class);
        Route::post('servers/{server}/command', [ServerConsoleController::class, 'command']);
    });

    // Server console & resources
    Route::get('servers/{server}/websocket', [ServerConsoleController::class, 'websocket']);
    Route::get('servers/{server}/resources', [ServerConsoleController::class, 'resources']);

    // Server files
    Route::prefix('servers/{server}/files')->group(function () {
        Route::get('/', [ServerFileController::class, 'list']);
        Route::get('content', [ServerFileController::class, 'content']);
        Route::post('write', [ServerFileController::class, 'write']);
        Route::post('rename', [ServerFileController::class, 'rename']);
        Route::post('delete', [ServerFileController::class, 'delete']);
        Route::post('compress', [ServerFileController::class, 'compress']);
        Route::post('decompress', [ServerFileController::class, 'decompress']);
        Route::post('create-folder', [ServerFileController::class, 'createFolder']);
    });

    // User profile
    Route::get('user/profile', [UserController::class, 'show']);
    Route::put('user/profile', [UserController::class, 'update']);
    Route::post('user/change-password', [UserController::class, 'changePassword']);
    Route::post('user/sftp-password', [UserController::class, 'sftpPassword']);
});
