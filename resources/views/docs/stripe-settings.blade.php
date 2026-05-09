@php
    $title = match ($current_locale ?? 'en') {
        'fr' => 'Peregrine — Réglages Stripe',
        default => 'Peregrine — Stripe integration settings',
    };
@endphp
@include('docs._layout', compact('title', 'content', 'available_locales', 'current_locale'))
