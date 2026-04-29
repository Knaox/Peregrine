@php
    $title = match ($current_locale ?? 'en') {
        'fr' => 'Peregrine — Plugins — Guide développeur & opérateur',
        default => 'Peregrine — Plugins — Developer & operator guide',
    };
@endphp
@include('docs._layout', compact('title', 'content', 'available_locales', 'current_locale'))
