{{--
    Shared shell for admin settings pages. Variables expected:
      $subtitle (optional string)
      $badges (optional array of ['label' => '…', 'color' => '…', 'icon' => 'heroicon-o-…'])
      $form (Filament form, $this->form)
      $actions (array, $this->getFormActions())

    Inline styles only — Filament's default theme does not compile arbitrary
    Tailwind classes from custom blade views.
--}}

@php
    $subtitle ??= null;
    $badges ??= [];

    $cardStyle = 'border-radius: 0.75rem; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); padding: 0.875rem 1.125rem; display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;';
    $subtitleStyle = 'flex: 1 1 auto; min-width: 14rem; font-size: 0.875rem; color: rgba(255,255,255,0.65); margin: 0;';
    $pillBase = 'display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500;';

    $colorMap = [
        'success' => 'background: rgba(34,197,94,0.12); color: rgb(74,222,128);',
        'warning' => 'background: rgba(245,158,11,0.12); color: rgb(252,211,77);',
        'danger'  => 'background: rgba(239,68,68,0.12); color: rgb(248,113,113);',
        'info'    => 'background: rgba(56,189,248,0.12); color: rgb(125,211,252);',
        'primary' => 'background: rgba(var(--primary-500), 0.15); color: rgb(var(--primary-300));',
        'gray'    => 'background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.7);',
    ];
@endphp

@if ($subtitle || ! empty($badges))
    <div style="{{ $cardStyle }}">
        @if ($subtitle)
            <p style="{{ $subtitleStyle }}">{{ $subtitle }}</p>
        @endif

        @foreach ($badges as $badge)
            @php $tone = $colorMap[$badge['color'] ?? 'gray'] ?? $colorMap['gray']; @endphp
            <span style="{{ $pillBase }} {{ $tone }}">
                @if (! empty($badge['icon']))
                    <x-filament::icon :icon="$badge['icon']" style="width: 0.875rem; height: 0.875rem;" />
                @endif
                {{ $badge['label'] }}
            </span>
        @endforeach
    </div>
@endif

<form wire:submit="save">
    {{ $form }}

    <div style="margin-top: 1.5rem; display: flex; flex-wrap: wrap; gap: 0.75rem;">
        @foreach ($actions as $action)
            {{ $action }}
        @endforeach
    </div>
</form>
