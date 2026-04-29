@php
    $title = match ($current_locale ?? 'en') {
        'fr' => 'Peregrine — Authentification — Architecture',
        default => 'Peregrine — Authentication architecture',
    };
@endphp
@include('docs._layout', compact('title', 'content', 'available_locales', 'current_locale'))
