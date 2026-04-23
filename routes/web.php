<?php

use App\Http\Controllers\Api\Auth\SocialAuthController;
use Illuminate\Support\Facades\Route;
use League\CommonMark\GithubFlavoredMarkdownConverter;

// Setup Wizard SPA
Route::view('/setup', 'setup');

// Bridge developer documentation — public HTML render of docs/bridge-api.md.
// Targeted at shop developers writing the client side of the Bridge contract.
Route::get('/docs/bridge-api', function () {
    $markdown = file_get_contents(base_path('docs/bridge-api.md')) ?: '';
    $converter = new GithubFlavoredMarkdownConverter([
        'html_input' => 'allow',
        'allow_unsafe_links' => false,
    ]);
    return view('docs.bridge-api', [
        'content' => (string) $converter->convert($markdown),
    ]);
})->name('docs.bridge-api');

// Bridge Paymenter operator guide — public HTML render of docs/bridge-paymenter.md.
// Targeted at admins wiring up Pelican outgoing webhooks → Peregrine in
// Paymenter mode (no Stripe, no plan push).
Route::get('/docs/bridge-paymenter', function () {
    $markdown = file_get_contents(base_path('docs/bridge-paymenter.md')) ?: '';
    $converter = new GithubFlavoredMarkdownConverter([
        'html_input' => 'allow',
        'allow_unsafe_links' => false,
    ]);
    return view('docs.bridge-paymenter', [
        'content' => (string) $converter->convert($markdown),
    ]);
})->name('docs.bridge-paymenter');

/*
 * OAuth social auth — MUST live in the web group so Socialite's session-based
 * state CSRF check works across the browser round-trip from the provider.
 * Keeping the /api/auth/social/* URL shape so the frontend and provider
 * redirect URIs don't change.
 */
Route::prefix('api/auth')->group(function () {
    Route::get('social/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->middleware('throttle:social-redirect')
        ->where('provider', 'shop|google|discord|linkedin|paymenter');
    Route::get('social/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->where('provider', 'shop|google|discord|linkedin|paymenter');
});

// Main SPA (catch-all for React Router — excludes admin, api, docs, livewire, sanctum, filament)
Route::view('/{any?}', 'app')->where('any', '^(?!admin|api|docs|livewire|filament|sanctum|storage|up).*$');
