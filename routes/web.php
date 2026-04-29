<?php

use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\PluginController;
use Illuminate\Support\Facades\Route;
use League\CommonMark\GithubFlavoredMarkdownConverter;

// Setup Wizard SPA
Route::view('/setup', 'setup');

// Frontend login page (React SPA route). The named `frontend.login` is what
// Filament's auth middleware redirects to when an admin hits /admin/* with
// no session — single sign-in entry point for the whole product.
Route::view('/login', 'app')->name('frontend.login');

// Safety net for old bookmarks pointing at /admin/login — Filament's panel
// no longer exposes a login page (admins use /login like everyone else),
// but a 404 on a deep bookmark is a worse experience than a clean redirect.
Route::redirect('/admin/login', '/login', 302);

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

// Pelican webhook receiver setup guide — public HTML render of docs/pelican-webhook.md.
// Decoupled from Bridge mode : works in any mode (shop_stripe, paymenter, disabled).
Route::get('/docs/pelican-webhook', function () {
    $markdown = file_get_contents(base_path('docs/pelican-webhook.md')) ?: '';
    $converter = new GithubFlavoredMarkdownConverter([
        'html_input' => 'allow',
        'allow_unsafe_links' => false,
    ]);
    return view('docs.pelican-webhook', [
        'content' => (string) $converter->convert($markdown),
    ]);
})->name('docs.pelican-webhook');

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

// Plugin JS bundle — controller fallback for the static symlink at
// public/plugins/{id} → plugins/{id}/frontend/dist. Nginx serves the symlink
// directly when it exists (fast path); when it's missing (fresh Docker
// installs with a named plugins volume, broken activation, etc.) the request
// falls through here so the browser still gets `application/javascript`
// instead of the SPA HTML — otherwise Cloudflare's `nosniff` makes the
// browser refuse to execute it and the plugin page renders blank.
Route::get('/plugins/{pluginId}/bundle.js', [PluginController::class, 'bundle'])
    ->where('pluginId', '[a-z0-9][a-z0-9-]*');

// Main SPA (catch-all for React Router — excludes admin, api, docs, livewire,
// sanctum, filament, storage, up, plugins). `plugins` is excluded so a missing
// plugin asset returns a clean 404 instead of HTML masquerading as JS/CSS.
Route::view('/{any?}', 'app')->where('any', '^(?!admin|api|docs|livewire|filament|sanctum|storage|up|plugins).*$');
