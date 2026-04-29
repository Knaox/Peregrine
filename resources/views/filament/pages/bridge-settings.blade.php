<x-filament-panels::page>
    @php
        $bridgeMode = app(\App\Services\Bridge\BridgeModeService::class)->current();
        $modeBadgeKey = match ($bridgeMode->value) {
            'shop_stripe' => 'admin.badges.bridge_mode.shop_stripe',
            'paymenter'   => 'admin.badges.bridge_mode.paymenter',
            default       => 'admin.badges.bridge_mode.disabled',
        };
        $modeBadgeColor = match ($bridgeMode->value) {
            'shop_stripe' => 'success',
            'paymenter'   => 'info',
            default       => 'gray',
        };
        $badges = [
            ['label' => __($modeBadgeKey), 'color' => $modeBadgeColor, 'icon' => 'heroicon-o-link'],
        ];
    @endphp

    @include('filament.pages.partials.settings-shell', [
        'subtitle' => __('admin.pages.bridge_settings.subtitle'),
        'badges' => $badges,
        'form' => $this->form,
        'actions' => $this->getFormActions(),
    ])
</x-filament-panels::page>
