<!DOCTYPE html>
@php
    // Resolve everything theme/branding-related up-front so the <html> element
    // itself can carry data-theme, preventing a one-frame FOUC.
    $branding = app(\App\Services\SettingsService::class)->getBranding();
    $logoHeight = $branding['logo_height'] ?? 40;
    $appName = $branding['app_name'] ?? config('app.name', 'Peregrine');
    $showName = $branding['show_app_name'] ?? true;

    $themeService = app(\App\Services\ThemeService::class);
    $userThemeMode = auth()->user()->theme_mode ?? 'auto';
    $initialMode = $userThemeMode === 'light' ? 'light' : 'dark';
    $initialCssVars = $themeService->getCssVariablesForMode($initialMode);

    // Pick the logo matching the resolved initial mode so the splash (rendered
    // server-side, before React) doesn't flash the wrong variant.
    $logoUrl = $initialMode === 'light' && ! empty($branding['logo_url_light'])
        ? $branding['logo_url_light']
        : ($branding['logo_url'] ?? '/images/logo.webp');

    // Default UI language picked by the admin in /admin/settings (en or fr).
    // The SPA's i18n config uses this as the fallback language when no
    // localStorage / browser preference matches a supported locale.
    $defaultLocale = app(\App\Services\SettingsService::class)->get('default_locale', 'en');

    // Pre-compile the i18n resource bundle for the user's effective locale
    // (their saved preference if logged in, otherwise the admin default) and
    // inline it as `window.__I18N_BUNDLE__` further down. Removes every
    // translation HTTP round-trip on first paint — the SPA boots with strings
    // already in the document. Net cost on the wire: ~12 KB gzipped per
    // locale. Cache-keyed by mtime, see I18nBootService.
    $i18nLocale = auth()->user()->locale ?? $defaultLocale;
    if (! in_array($i18nLocale, ['en', 'fr'], true)) {
        $i18nLocale = $defaultLocale;
    }
    $i18nBundle = app(\App\Services\I18n\I18nBootService::class)->bootstrap($i18nLocale);
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $initialMode }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Reverb config consumed by `resources/js/services/echo.ts` to
         drive live mirror-update broadcasts on /server/{id}/* pages.
         Empty values disable the Echo client gracefully — the SPA
         continues to work with its default TanStack staleTime.

         REVERB_HOST default = the current request's hostname so a panel
         accessed via several URLs (localhost during dev, LAN IP from a
         laptop, ngrok tunnel for sharing, …) never dials a stale literal
         "localhost" — that would resolve to the BROWSER's machine, not
         the server's, and silently kill every WebSocket subscription.
         echo.ts has a second JS-side guard for the same situation. --}}
    <meta name="reverb-key" content="{{ config('broadcasting.connections.reverb.key', '') }}">
    <meta name="reverb-host" content="{{ env('REVERB_HOST') ?: request()->getHost() }}">
    <meta name="reverb-port" content="{{ env('REVERB_PORT', '443') }}">
    <meta name="reverb-scheme" content="{{ env('REVERB_SCHEME', 'https') }}">
    <title>{{ $appName }}</title>
    <link rel="icon" href="{{ $branding['favicon_url'] ?? '/images/favicon.ico' }}" type="image/x-icon">
    <script>window.__BRANDING__ = @json($branding);</script>
    <script>window.__THEME_MODE__ = @json($userThemeMode);</script>
    <script>window.__DEFAULT_LOCALE__ = @json($defaultLocale);</script>
    {{-- Inlined i18n bundle for the user's effective locale. Read by
         resources/js/i18n/config.ts at boot — zero fetch, zero waterfall,
         no flash of untranslated content even on slow mobile networks. --}}
    <script>window.__I18N_BUNDLE__ = @json(['locale' => $i18nLocale, 'resources' => $i18nBundle]);</script>
    <style>:root {
        @foreach($initialCssVars as $key => $value){{ $key }}: {{ $value }};
        @endforeach
    }</style>
    {{-- Preload egg banner images so cards render instantly --}}
    @auth
        @php
            $eggs = \App\Models\Egg::whereNotNull('banner_image')->pluck('banner_image')->unique();
        @endphp
        @foreach($eggs as $img)
            <link rel="preload" as="image" href="{{ asset('storage/' . $img) }}">
        @endforeach
    @endauth
    <style>
        body { margin: 0; background: var(--color-background); }
        #splash { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: var(--color-background); transition: opacity 0.3s ease; }
        #splash.hidden { opacity: 0; pointer-events: none; }
        #splash-logo { height: {{ $logoHeight }}px; }
        #splash-name { color: var(--color-text-primary); font-family: Inter, system-ui, sans-serif; font-size: 1.25rem; font-weight: 600; margin-left: 12px; }
    </style>
    @viteReactRefresh
    @vite(['resources/js/app.tsx'])
</head>
<body class="antialiased">
    {{-- Instant splash with logo — visible before React loads --}}
    <div id="splash">
        <img id="splash-logo" src="{{ $logoUrl }}" alt="{{ $appName }}">
        @if($showName)
            <span id="splash-name">{{ $appName }}</span>
        @endif
    </div>
    <div id="app"></div>
</body>
</html>
