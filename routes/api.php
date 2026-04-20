<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\OAuthController;
use App\Http\Controllers\Api\PluginController;
use App\Http\Controllers\Api\ServerBackupController;
use App\Http\Controllers\Api\ServerConsoleController;
use App\Http\Controllers\Api\ServerController;
use App\Http\Controllers\Api\ServerDatabaseController;
use App\Http\Controllers\Api\ServerFileController;
use App\Http\Controllers\Api\ServerNetworkController;
use App\Http\Controllers\Api\ServerPowerController;
use App\Http\Controllers\Api\ServerScheduleController;
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

// Plugins (public — active plugins for frontend)
Route::get('plugins', [PluginController::class, 'index']);

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

    // Server startup variables
    Route::get('servers/{server}/startup', [ServerController::class, 'startupVariables']);
    Route::put('servers/{server}/startup/variable', [ServerController::class, 'updateStartupVariable']);

    // Server console & resources
    Route::get('servers/{server}/websocket', [ServerConsoleController::class, 'websocket']);
    Route::get('servers/{server}/resources', [ServerConsoleController::class, 'resources']);

    // Server files
    Route::prefix('servers/{server}/files')->group(function () {
        Route::get('/', [ServerFileController::class, 'list']);
        Route::get('content', [ServerFileController::class, 'content']);
        Route::get('download', [ServerFileController::class, 'download']);
        Route::post('write', [ServerFileController::class, 'write']);
        Route::post('rename', [ServerFileController::class, 'rename']);
        Route::post('delete', [ServerFileController::class, 'delete']);
        Route::post('compress', [ServerFileController::class, 'compress']);
        Route::post('decompress', [ServerFileController::class, 'decompress']);
        Route::post('copy', [ServerFileController::class, 'copy']);
        Route::get('upload-url', [ServerFileController::class, 'uploadUrl']);
        Route::post('create-folder', [ServerFileController::class, 'createFolder']);
        Route::post('chmod', [ServerFileController::class, 'chmod']);
        Route::post('pull', [ServerFileController::class, 'pull']);
    });

    // Server databases
    Route::get('servers/{server}/databases', [ServerDatabaseController::class, 'index']);
    Route::post('servers/{server}/databases', [ServerDatabaseController::class, 'store']);
    Route::post('servers/{server}/databases/{database}/rotate-password', [ServerDatabaseController::class, 'rotatePassword']);
    Route::delete('servers/{server}/databases/{database}', [ServerDatabaseController::class, 'destroy']);

    // Server backups
    Route::get('servers/{server}/backups', [ServerBackupController::class, 'index']);
    Route::post('servers/{server}/backups', [ServerBackupController::class, 'store']);
    Route::get('servers/{server}/backups/{backup}/download', [ServerBackupController::class, 'download']);
    Route::post('servers/{server}/backups/{backup}/lock', [ServerBackupController::class, 'toggleLock']);
    Route::post('servers/{server}/backups/{backup}/restore', [ServerBackupController::class, 'restore']);
    Route::delete('servers/{server}/backups/{backup}', [ServerBackupController::class, 'destroy']);

    // Server schedules
    Route::get('servers/{server}/schedules', [ServerScheduleController::class, 'index']);
    Route::post('servers/{server}/schedules', [ServerScheduleController::class, 'store']);
    Route::post('servers/{server}/schedules/{schedule}', [ServerScheduleController::class, 'update']);
    Route::post('servers/{server}/schedules/{schedule}/execute', [ServerScheduleController::class, 'execute']);
    Route::delete('servers/{server}/schedules/{schedule}', [ServerScheduleController::class, 'destroy']);
    Route::post('servers/{server}/schedules/{schedule}/tasks', [ServerScheduleController::class, 'storeTask']);
    Route::delete('servers/{server}/schedules/{schedule}/tasks/{task}', [ServerScheduleController::class, 'destroyTask']);

    // Server network
    Route::get('servers/{server}/network', [ServerNetworkController::class, 'index']);
    Route::post('servers/{server}/network', [ServerNetworkController::class, 'store']);
    Route::post('servers/{server}/network/{allocation}/notes', [ServerNetworkController::class, 'updateNotes']);
    Route::post('servers/{server}/network/{allocation}/primary', [ServerNetworkController::class, 'setPrimary']);
    Route::delete('servers/{server}/network/{allocation}', [ServerNetworkController::class, 'destroy']);

    // Admin: plugins management
    Route::middleware('admin')->prefix('admin/plugins')->group(function () {
        Route::get('/', [PluginController::class, 'all']);
        Route::post('{pluginId}/activate', [PluginController::class, 'activate']);
        Route::post('{pluginId}/deactivate', [PluginController::class, 'deactivate']);
        Route::delete('{pluginId}', [PluginController::class, 'uninstall']);
        Route::get('{pluginId}/settings', [PluginController::class, 'settings']);
        Route::put('{pluginId}/settings', [PluginController::class, 'updateSettings']);
    });

    // Admin: marketplace
    Route::middleware('admin')->prefix('admin/marketplace')->group(function () {
        Route::get('/', [MarketplaceController::class, 'index']);
        Route::post('{pluginId}/install', [MarketplaceController::class, 'install']);
        Route::post('{pluginId}/update', [MarketplaceController::class, 'update']);
        Route::get('check-updates', [MarketplaceController::class, 'checkUpdates']);
    });

    // User profile
    Route::get('user/profile', [UserController::class, 'show']);
    Route::put('user/profile', [UserController::class, 'update']);
    Route::post('user/change-password', [UserController::class, 'changePassword']);
    Route::post('user/sftp-password', [UserController::class, 'sftpPassword']);
    Route::get('user/dashboard-layout', [UserController::class, 'getDashboardLayout']);
    Route::put('user/dashboard-layout', [UserController::class, 'updateDashboardLayout']);
});
