{{--
    Shared shell for admin settings pages (Settings, AuthSettings, BridgeSettings,
    ThemeSettings, EmailTemplates, PelicanWebhookSettings).
    Used via @include('filament.pages.partials.settings-shell', [...]).

    Variables:
      - $subtitle (optional, string): caption under the title
      - $badges (optional, array of [label, color]): pill badges shown next to the title
      - $form (required): bound by parent layout via $this->form
--}}

@php
    $subtitle ??= null;
    $badges ??= [];
@endphp

<div class="space-y-6">
    @if ($subtitle || ! empty($badges))
        <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-200/60 bg-white/40 px-4 py-3 dark:border-white/5 dark:bg-white/[0.02]">
            @if ($subtitle)
                <p class="flex-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ $subtitle }}
                </p>
            @endif

            @foreach ($badges as $badge)
                @php
                    $color = $badge['color'] ?? 'gray';
                    $colorClasses = match ($color) {
                        'success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300 ring-emerald-200/60 dark:ring-emerald-400/20',
                        'warning' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300 ring-amber-200/60 dark:ring-amber-400/20',
                        'danger'  => 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300 ring-rose-200/60 dark:ring-rose-400/20',
                        'info'    => 'bg-sky-100 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300 ring-sky-200/60 dark:ring-sky-400/20',
                        'primary' => 'bg-primary-100 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300 ring-primary-200/60 dark:ring-primary-400/20',
                        default   => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300 ring-gray-200/60 dark:ring-white/10',
                    };
                @endphp
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {{ $colorClasses }}">
                    @if (! empty($badge['icon']))
                        <x-filament::icon :icon="$badge['icon']" class="h-3.5 w-3.5" />
                    @endif
                    {{ $badge['label'] }}
                </span>
            @endforeach
        </div>
    @endif

    <form wire:submit="save" class="space-y-6">
        {{ $form }}

        <div class="sticky bottom-0 -mx-4 mt-6 flex flex-wrap items-center gap-3 border-t border-gray-200/60 bg-white/80 px-4 py-3 backdrop-blur dark:border-white/5 dark:bg-gray-950/70 sm:-mx-6 sm:px-6">
            @foreach ($actions as $action)
                {{ $action }}
            @endforeach
        </div>
    </form>
</div>
