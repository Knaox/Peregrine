@php
    $title = match ($current_locale ?? 'en') {
        'fr' => 'Peregrine — Bridge — Orchestrateur webhook — Guide opérateur',
        default => 'Peregrine Bridge — Webhook Orchestrator — Setup Guide',
    };
@endphp
@include('docs._layout', compact('title', 'content', 'available_locales', 'current_locale'))
