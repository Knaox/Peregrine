<x-filament-panels::page>
    @php
        $integrations = app(\App\Services\Integrations\IntegrationStatusService::class);
        $hasStripe = $integrations->hasStripeConfigured();
        $hasShop = $integrations->hasActiveShop();
        $badges = [
            [
                'label' => $hasStripe || $hasShop
                    ? __('admin/_shell.badges.bridge_mode.shop_stripe')
                    : __('admin/_shell.badges.bridge_mode.disabled'),
                'color' => $hasStripe || $hasShop ? 'success' : 'gray',
                'icon' => 'heroicon-o-link',
            ],
        ];
    @endphp

    @include('filament.pages.partials.settings-shell', [
        'subtitle' => __('admin/auth_settings.page.subtitle'),
        'badges' => $badges,
        'form' => $this->form,
        'actions' => $this->getFormActions(),
    ])
</x-filament-panels::page>
