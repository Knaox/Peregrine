<x-filament-panels::page>
    @php
        $preset = app(\App\Services\SettingsService::class)->get('theme_preset', 'orange');
        $mode = app(\App\Services\SettingsService::class)->get('theme_mode', 'dark');
        $badges = [
            ['label' => 'Preset: ' . ucfirst((string) $preset), 'color' => 'primary', 'icon' => 'heroicon-o-sparkles'],
            ['label' => ucfirst((string) $mode), 'color' => 'gray', 'icon' => 'heroicon-o-moon'],
        ];
    @endphp

    @include('filament.pages.partials.settings-shell', [
        'subtitle' => __('admin.pages.theme_settings.subtitle'),
        'badges' => $badges,
        'form' => $this->form,
        'actions' => $this->getFormActions(),
    ])
</x-filament-panels::page>
