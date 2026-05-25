@php $default = app()->getLocale() === 'en' ? 'en' : 'fr'; @endphp
<div x-data="{ lang: '{{ $default }}' }" style="font-size:0.85rem;line-height:1.6;">
    <div style="display:flex;gap:0.4rem;margin-bottom:1.1rem;">
        <button type="button" x-on:click="lang='fr'"
            style="padding:0.35rem 0.9rem;border-radius:0.45rem;border:1px solid rgba(148,163,184,0.4);font-size:0.78rem;font-weight:600;cursor:pointer;transition:background .15s ease,color .15s ease,border-color .15s ease;"
            x-bind:style="lang==='fr' ? 'background:#f97316;border-color:#f97316;color:#fff;' : 'background:transparent;color:inherit;'">Français</button>
        <button type="button" x-on:click="lang='en'"
            style="padding:0.35rem 0.9rem;border-radius:0.45rem;border:1px solid rgba(148,163,184,0.4);font-size:0.78rem;font-weight:600;cursor:pointer;transition:background .15s ease,color .15s ease,border-color .15s ease;"
            x-bind:style="lang==='en' ? 'background:#f97316;border-color:#f97316;color:#fff;' : 'background:transparent;color:inherit;'">English</button>
    </div>

    <div x-show="lang==='fr'" @if($default!=='fr') style="display:none;" @endif>
        @include('peregrine-phpmyadmin::partials.guide-fr', ['ctx' => $ctx])
    </div>
    <div x-show="lang==='en'" @if($default!=='en') style="display:none;" @endif>
        @include('peregrine-phpmyadmin::partials.guide-en', ['ctx' => $ctx])
    </div>
</div>
