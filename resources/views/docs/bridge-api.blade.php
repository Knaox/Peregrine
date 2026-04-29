@php
    $title = match ($current_locale ?? 'en') {
        'fr' => 'Peregrine — API Bridge — Documentation développeur',
        default => 'Peregrine Bridge API — Developer Documentation',
    };
@endphp
@include('docs._layout', compact('title', 'content', 'available_locales', 'current_locale'))
