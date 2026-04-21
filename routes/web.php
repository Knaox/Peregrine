<?php

use App\Http\Controllers\Api\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;

// Setup Wizard SPA
Route::view('/setup', 'setup');

/*
 * OAuth social auth — MUST live in the web group so Socialite's session-based
 * state CSRF check works across the browser round-trip from the provider.
 * Keeping the /api/auth/social/* URL shape so the frontend and provider
 * redirect URIs don't change.
 */
Route::prefix('api/auth')->group(function () {
    Route::get('social/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->middleware('throttle:social-redirect')
        ->where('provider', 'shop|google|discord|linkedin');
    Route::get('social/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->where('provider', 'shop|google|discord|linkedin');
});

// Main SPA (catch-all for React Router — excludes admin, api, docs, livewire, sanctum, filament)
Route::view('/{any?}', 'app')->where('any', '^(?!admin|api|docs|livewire|filament|sanctum|storage|up).*$');
