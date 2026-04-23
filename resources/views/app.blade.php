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
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $initialMode }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $appName }}</title>
    <link rel="icon" href="{{ $branding['favicon_url'] ?? '/images/favicon.ico' }}" type="image/x-icon">
    <script>window.__BRANDING__ = @json($branding);</script>
    <script>window.__THEME_MODE__ = @json($userThemeMode);</script>
    <script>window.__DEFAULT_LOCALE__ = @json($defaultLocale);</script>
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
