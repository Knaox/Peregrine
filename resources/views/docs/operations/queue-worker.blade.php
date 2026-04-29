@php
    $title = match ($current_locale ?? 'en') {
        'fr' => 'Peregrine — Worker queue — Guide opérateur',
        default => 'Peregrine — Queue worker — Operator guide',
    };
@endphp
@include('docs._layout', compact('title', 'content', 'available_locales', 'current_locale'))
