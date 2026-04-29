@php
    $title = match ($current_locale ?? 'en') {
        'fr' => 'Peregrine — OAuth WHMCS — Guide de configuration',
        default => 'Peregrine — WHMCS OAuth — Setup Guide',
    };
@endphp
@include('docs._layout', compact('title', 'content', 'available_locales', 'current_locale'))
