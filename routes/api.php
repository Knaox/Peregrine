<?php

use App\Http\Controllers\Api\Admin\AdminServersController;
use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\Auth\TwoFactorController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Bridge\PelicanWebhookController;
use App\Http\Controllers\Api\Bridge\PlanSyncController;
use App\Http\Controllers\Api\Bridge\StripeWebhookController;
use App\Http\Middleware\VerifyBridgeSignature;
use App\Http\Middleware\VerifyPelicanWebhookToken;
use App\Http\Middleware\VerifyStripeSignature;
use App\Http\Controllers\Api\MarketplaceController;
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
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('register', [AuthController::class, 'register']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);

    // 2FA — the challenge is unauthenticated (pre-login), the rest are auth'd.
    Route::post('2fa/challenge', [TwoFactorController::class, 'challenge'])
        ->middleware('throttle:2fa-challenge');

    Route::middleware('auth')->prefix('2fa')->group(function () {
        Route::post('setup', [TwoFactorController::class, 'setup'])->middleware('throttle:2fa-setup');
        Route::post('confirm', [TwoFactorController::class, 'confirm']);
        Route::post('disable', [TwoFactorController::class, 'disable']);
        Route::post('recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes']);
    });

    // Social auth (Shop + Paymenter + Google + Discord + LinkedIn) — configurable via Filament
    // NOTE: redirect/callback are declared in routes/web.php (session-backed OAuth state).
    Route::get('providers', [SocialAuthController::class, 'listProviders']);
    Route::middleware('auth')->group(function () {
        Route::get('identities', [SocialAuthController::class, 'listLinked']);
        Route::delete('social/{provider}/unlink', [SocialAuthController::class, 'unlink'])
            ->where('provider', 'shop|google|discord|linkedin|paymenter');
    });
});

// Bridge — Shop pushes plan definitions to Peregrine via signed HTTP API.
// Public routes (no Laravel auth) — protected by HMAC signature middleware.
// Throttled per-IP to 60/min (Shop is the only legitimate caller).
Route::prefix('bridge')
    ->middleware(['throttle:bridge', VerifyBridgeSignature::class])
    ->group(function () {
        Route::post('ping', [PlanSyncController::class, 'ping']);
        Route::post('plans/upsert', [PlanSyncController::class, 'upsert']);
        Route::delete('plans/{shopPlanId}', [PlanSyncController::class, 'destroy'])
            ->whereNumber('shopPlanId');
    });

// Stripe webhook receiver. Public route (no Laravel auth) — protected
// by signature middleware. Tolerant rate limit since Stripe has fixed IPs
// and spikes can happen during dunning runs.
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->middleware(['throttle:stripe-webhook', VerifyStripeSignature::class])
    ->name('stripe.webhook');

// Pelican outgoing webhook receiver. Public route authenticated by bearer
// token (Pelican does not sign payloads). The middleware also gates on
// the standalone `pelican_webhook_enabled` toggle so events sent to a
// disabled receiver fail with 503.
Route::post('pelican/webhook', [PelicanWebhookController::class, 'handle'])
    ->middleware(['throttle:pelican-webhook', VerifyPelicanWebhookToken::class])
    ->name('pelican.webhook');

// Heartbeat for browser tests / Pelican URL validation. Returns 200 with
// the receiver's status so admins poking the URL in a browser don't see a
// 405 unhandled exception in error monitoring. No auth — the response only
// reflects the public toggle state, no secrets.
Route::get('pelican/webhook', function () {
    $enabled = (string) app(\App\Services\SettingsService::class)
        ->get('pelican_webhook_enabled', 'false');
    $isEnabled = $enabled === 'true' || $enabled === '1';

    return response()->json([
        'service' => 'pelican-webhook-receiver',
        'method' => 'POST',
        'enabled' => $isEnabled,
        'message' => $isEnabled
            ? 'Receiver is ready. Send Pelican webhook events as POST with Authorization: Bearer <token>.'
            : 'Receiver is currently disabled. Enable it from /admin/pelican-webhook-settings.',
    ]);
})->name('pelican.webhook.status');

// Plugins (public — active plugins for frontend)
Route::get('plugins', [PluginController::class, 'index']);
// Plugin i18n bundle (public). Locale matches /^[a-z]{2}(-[A-Z]{2})?$/.
Route::get('plugins/{pluginId}/i18n/{locale}', [PluginController::class, 'i18n'])
    ->where('locale', '[a-z]{2}(-[A-Z]{2})?');

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

    // Server settings (rename / reinstall) — rate-limited like power actions
    // because reinstall is irreversible and triggers egg install scripts.
    Route::middleware('throttle:server-actions')->group(function () {
        Route::post('servers/{server}/rename', [ServerController::class, 'rename']);
        Route::post('servers/{server}/reinstall', [ServerController::class, 'reinstall']);
    });

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

    // Admin: cross-user server listing (admin mode dashboard). Wrapped in
    // the two-factor middleware so the tightening of `auth_2fa_required_admins`
    // applies consistently with the Filament panel.
    Route::middleware(['admin', 'two-factor'])->get('admin/servers', [AdminServersController::class, 'index']);

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
