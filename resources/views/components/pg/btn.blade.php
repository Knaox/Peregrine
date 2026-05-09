{{--
    <x-pg.btn variant="primary|success|danger|warning|default|ghost"
              :loading-target="'updatePlugin('.$id.')'"
              wire:click="..." />

    Anonymous Blade component used across the /admin/plugins page so we
    don't repeat the same `pg-btn pg-btn-XXX` boilerplate dozens of
    times. Pure presentation — no Livewire knowledge baked in, the
    consumer passes wire:* attributes straight through via
    $attributes->merge() / Blade attribute bag.

    Props :
      variant       — visual style key (default: 'default')
      loadingTarget — when set, the button auto-disables on Livewire
                      activity matching this wire:target. The slot is
                      hidden during loading and replaced by an ellipsis
                      ($loading slot wins if provided).
      href          — render as <a> instead of <button>
      type          — button type (default: 'button')

    Slots :
      default — button label (and inline icons if needed)
      loading — optional alternate label shown while wire:target fires
--}}
@props([
    'variant' => 'default',
    'loadingTarget' => null,
    'href' => null,
    'type' => 'button',
])

@php
    $tag = $href ? 'a' : 'button';
    $classes = ['pg-btn', 'pg-btn-' . $variant];
@endphp

<{{ $tag }}
    @if($tag === 'button') type="{{ $type }}" @endif
    @if($href) href="{{ $href }}" @endif
    @if($loadingTarget)
        wire:loading.attr="disabled"
        wire:target="{{ $loadingTarget }}"
    @endif
    {{ $attributes->class($classes) }}
>
    @if($loadingTarget)
        <span wire:loading.remove wire:target="{{ $loadingTarget }}" style="display: inline-flex; align-items: center; gap: 0.4rem;">
            {{ $slot }}
        </span>
        <span wire:loading wire:target="{{ $loadingTarget }}">
            {{ $loading ?? '…' }}
        </span>
    @else
        {{ $slot }}
    @endif
</{{ $tag }}>
