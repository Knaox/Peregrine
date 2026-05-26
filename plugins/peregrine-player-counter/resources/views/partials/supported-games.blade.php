@php
    $games = (array) config('peregrine-player-counter.supported_games', []);
    $ns = 'peregrine-player-counter::messages.settings.';

    // Inline styles only: Tailwind does not scan plugin Blade files, so utility
    // classes wouldn't be compiled into the panel CSS. rgba tints adapt to both
    // light and dark; text inherits the panel's colour so it stays readable.
    $box = 'margin:0 0 1.5rem;padding:0.9rem 1.1rem;border-radius:0.75rem;border:1px solid rgba(245,158,11,0.40);background:rgba(245,158,11,0.10);font-size:0.875rem;line-height:1.45;';
    $head = 'display:flex;align-items:center;gap:0.5rem;font-weight:700;';
    $list = 'margin:0.5rem 0 0;padding-left:1.15rem;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0.05rem 1rem;';
    $note = 'margin:0.6rem 0 0;font-size:0.78rem;opacity:0.7;';
@endphp

@if ($games !== [])
    <div style="{{ $box }}">
        <div style="{{ $head }}">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="flex-shrink:0;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
            </svg>
            {{ __($ns . 'supported_title') }}
        </div>
        <p style="margin:0.4rem 0 0;">{{ __($ns . 'supported_intro') }}</p>
        <ul style="{{ $list }}">
            @foreach ($games as $game)
                <li>{{ $game }}</li>
            @endforeach
        </ul>
        <p style="{{ $note }}">{{ __($ns . 'supported_note') }}</p>
    </div>
@endif
