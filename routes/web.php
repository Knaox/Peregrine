<?php

use App\Http\Controllers\Admin\LocaleController;
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

// Locale switcher wired into the Filament user menu (top-right). The link
// is admin-only by virtue of being inside /admin/* + auth middleware on
// the panel — a Filament MenuItem just emits a regular GET link.
Route::get('/admin/locale/{locale}', [LocaleController::class, 'switch'])
    ->middleware(['web', 'auth'])
    ->where('locale', 'en|fr')
    ->name('admin.locale.switch');

// Public-facing operator/developer markdown docs. Each route serves the
// FR translation when the request locale resolves to `fr` AND a sibling
// `*.fr.md` file exists, otherwise falls back to the EN base file. Locale
// itself is set by `App\Http\Middleware\SetUserLocale` from the
// authenticated user, the admin-wide `default_locale` setting, then
// `config('app.locale')`.
$renderDoc = function (string $slug, string $view): \Illuminate\Http\Response|\Illuminate\View\View {
    // Locale resolution order : explicit `?lang=` query param wins, then
    // SetUserLocale-driven app()->getLocale(). Public docs need the URL
    // override so anonymous visitors can switch language without an admin
    // session.
    $requested = (string) request()->query('lang', '');
    $locale = in_array($requested, ['en', 'fr'], true)
        ? $requested
        : app()->getLocale();

    $localised = base_path("docs/{$slug}.{$locale}.md");
    $path = is_file($localised) ? $localised : base_path("docs/{$slug}.md");

    if (! is_file($path)) {
        abort(404);
    }

    $markdown = (string) file_get_contents($path);
    $converter = new GithubFlavoredMarkdownConverter([
        'html_input' => 'allow',
        'allow_unsafe_links' => false,
    ]);

    return view($view, [
        'content' => (string) $converter->convert($markdown),
        'available_locales' => collect(['en', 'fr'])
            ->filter(fn (string $l) => $l === 'en' || is_file(base_path("docs/{$slug}.{$l}.md")))
            ->values()
            ->all(),
        'current_locale' => $locale,
    ]);
};

// Bridge developer documentation — Bridge contract for shop developers.
Route::get('/docs/bridge-api', fn () => $renderDoc('bridge-api', 'docs.bridge-api'))
    ->name('docs.bridge-api');

// Bridge — webhook orchestrator operator guide (Paymenter, WHMCS, …).
Route::get('/docs/bridge-webhook-orchestrator', fn () => $renderDoc('bridge-webhook-orchestrator', 'docs.bridge-webhook-orchestrator'))
    ->name('docs.bridge-webhook-orchestrator');

// Backward-compat redirect — old URL kept stable for any external link.
Route::redirect('/docs/bridge-paymenter', '/docs/bridge-webhook-orchestrator', 301);

// WHMCS OAuth setup guide.
Route::get('/docs/whmcs-oauth-setup', fn () => $renderDoc('whmcs-oauth-setup', 'docs.whmcs-oauth-setup'))
    ->name('docs.whmcs-oauth-setup');

// Pelican webhook receiver setup guide. Works in any Bridge mode.
Route::get('/docs/pelican-webhook', fn () => $renderDoc('pelican-webhook', 'docs.pelican-webhook'))
    ->name('docs.pelican-webhook');

// Authentication architecture (multi-provider, 2FA, OAuth canonical IdPs).
Route::get('/docs/authentication', fn () => $renderDoc('authentication', 'docs.authentication'))
    ->name('docs.authentication');

// Plugin developer & operator guide.
Route::get('/docs/plugins', fn () => $renderDoc('plugins', 'docs.plugins'))
    ->name('docs.plugins');

// Queue worker setup (bare metal + supervisor + systemd).
Route::get('/docs/operations/queue-worker', fn () => $renderDoc('operations/queue-worker', 'docs.operations.queue-worker'))
    ->name('docs.operations.queue-worker');

/*
 * OAuth social auth — MUST live in the web group so Socialite's session-based
 * state CSRF check works across the browser round-trip from the provider.
 * Keeping the /api/auth/social/* URL shape so the frontend and provider
 * redirect URIs don't change.
 */
Route::prefix('api/auth')->group(function () {
    Route::get('social/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->middleware('throttle:social-redirect')
        ->where('provider', 'shop|google|discord|linkedin|paymenter|whmcs');
    Route::get('social/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->where('provider', 'shop|google|discord|linkedin|paymenter|whmcs');
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
