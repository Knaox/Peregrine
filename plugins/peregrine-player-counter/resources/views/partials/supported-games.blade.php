@php
    $ns = 'peregrine-player-counter::messages.settings.';
    $count = count((array) config('peregrine-player-counter.games', []));

    // Inline styles only: Tailwind does not scan plugin Blade files, so utility
    // classes wouldn't be compiled into the panel CSS. rgba tints adapt to both
    // light and dark; text inherits the panel's colour so it stays readable.
    $box = 'margin:0 0 1.5rem;padding:0.9rem 1.1rem;border-radius:0.75rem;border:1px solid rgba(20,184,166,0.40);background:rgba(20,184,166,0.10);font-size:0.875rem;line-height:1.45;';
    $head = 'display:flex;align-items:center;gap:0.5rem;font-weight:700;';
    $note = 'margin:0.6rem 0 0;font-size:0.78rem;opacity:0.7;';
@endphp

<div style="{{ $box }}">
    <div style="{{ $head }}">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#14b8a6" stroke-width="2" style="flex-shrink:0;">
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z" />
        </svg>
        {{ __($ns . 'supported_title') }}
    </div>
    <p style="margin:0.4rem 0 0;">{{ __($ns . 'supported_intro', ['count' => $count]) }}</p>
    <p style="{{ $note }}">{{ __($ns . 'supported_note') }}</p>
</div>
