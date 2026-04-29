<x-filament-panels::page>
    @php
        $enabled = (string) app(\App\Services\SettingsService::class)->get('pelican_webhook_enabled', 'false');
        $isEnabled = $enabled === 'true' || $enabled === '1';
        $badges = [
            $isEnabled
                ? ['label' => 'Receiver active', 'color' => 'success', 'icon' => 'heroicon-o-bolt']
                : ['label' => 'Receiver disabled', 'color' => 'gray', 'icon' => 'heroicon-o-power'],
        ];
    @endphp

    @include('filament.pages.partials.settings-shell', [
        'subtitle' => __('admin.pages.pelican_webhook_settings.subtitle'),
        'badges' => $badges,
        'form' => $this->form,
        'actions' => $this->getFormActions(),
    ])
</x-filament-panels::page>
