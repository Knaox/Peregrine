{{--
    <x-pg.pill variant="active|inactive|installed|external" :dot="true">
        Active
    </x-pg.pill>

    Status pill used on plugin cards and badges. The `dot` prop adds a
    leading colored dot — currently the convention for the "Active"
    pill (one and only place where motion/punctuation is welcome).

    Props :
      variant — active | inactive | installed | external (default: active)
      dot     — bool, prepend a small colored dot (default: false)
--}}
@props([
    'variant' => 'active',
    'dot' => false,
])

<span {{ $attributes->class(['pg-pill', 'pg-pill-' . $variant]) }}>
    @if($dot)
        <span class="pg-dot"></span>
    @endif
    {{ $slot }}
</span>
