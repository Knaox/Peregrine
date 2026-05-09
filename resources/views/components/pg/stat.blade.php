{{--
    <x-pg.stat
        accent="primary|success|warning|info"
        :value="$count"
        :label="__('admin/plugins.stats.installed')"
        x-on:click="tab = 'installed'; statusFilter = 'all'"
        ::class="{ 'is-active': tab === 'installed' && statusFilter === 'all' }"
    >
        <x-slot name="icon">
            <svg …/>
        </x-slot>
    </x-pg.stat>

    Big-number stat card. Active state is owned by Alpine via the
    standard `::class` attribute pass-through (Blade's `$attributes`
    bag preserves Alpine bindings). No `active` prop here so the
    component stays a pure presentational shell.
--}}
@props([
    'accent' => 'primary',
    'value' => 0,
    'label' => '',
])

@php
    $accentClass = match ($accent) {
        'success' => 'pg-stat-success',
        'warning' => 'pg-stat-warning',
        'info'    => 'pg-stat-info',
        default   => '',
    };
@endphp

<button
    type="button"
    {{ $attributes->class(['pg-stat', $accentClass]) }}
>
    <div class="pg-stat-row">
        <span class="pg-stat-icon">{{ $icon }}</span>
    </div>
    <div class="pg-stat-value">{{ $value }}</div>
    <div class="pg-stat-label">{{ $label }}</div>
</button>
