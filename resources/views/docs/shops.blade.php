@php
    $title = match ($current_locale ?? 'en') {
        'fr' => 'Peregrine — Shops — Guide d\'intégration multi-shop',
        default => 'Peregrine — Shops — Multi-shop integration guide',
    };
@endphp
@include('docs._layout', compact('title', 'content', 'available_locales', 'current_locale'))
