<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Peregrine') }} - Setup</title>
    <link rel="icon" href="/images/favicon.svg" type="image/svg+xml">
    @viteReactRefresh
    @vite(['resources/js/setup/main.tsx'])
</head>
<body class="antialiased">
    <div id="app"></div>
</body>
</html>
