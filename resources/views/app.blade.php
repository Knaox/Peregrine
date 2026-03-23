<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $branding = app(\App\Services\SettingsService::class)->getBranding();
        $logoUrl = $branding['logo_url'] ?? '/images/logo.webp';
        $logoHeight = $branding['logo_height'] ?? 40;
        $appName = $branding['app_name'] ?? config('app.name', 'Peregrine');
        $showName = $branding['show_app_name'] ?? true;
    @endphp
    <title>{{ $appName }}</title>
    <link rel="icon" href="{{ $branding['favicon_url'] ?? '/images/favicon.ico' }}" type="image/x-icon">
    <script>window.__BRANDING__ = @json($branding);</script>
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
        body { margin: 0; background: #0c0a14; }
        #splash { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: #0c0a14; transition: opacity 0.3s ease; }
        #splash.hidden { opacity: 0; pointer-events: none; }
        #splash-logo { height: {{ $logoHeight }}px; }
        #splash-name { color: #f1f0f5; font-family: Inter, system-ui, sans-serif; font-size: 1.25rem; font-weight: 600; margin-left: 12px; }
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
