<x-filament-panels::page>
    @php
        $integrations = app(\App\Services\Integrations\IntegrationStatusService::class);
        $hasStripeWebhook = $integrations->hasStripeConfigured();
        $hasStripeApi = $integrations->hasStripeApiKey();
        $hasShop = $integrations->hasActiveShop();

        $badges = [
            [
                'label' => $hasStripeWebhook
                    ? __('admin/stripe_settings.badges.webhook_configured')
                    : __('admin/stripe_settings.badges.webhook_missing'),
                'color' => $hasStripeWebhook ? 'success' : 'warning',
                'icon' => 'heroicon-o-credit-card',
            ],
            [
                'label' => $hasShop
                    ? __('admin/stripe_settings.badges.shop_configured')
                    : __('admin/stripe_settings.badges.shop_missing'),
                'color' => $hasShop ? 'success' : 'gray',
                'icon' => 'heroicon-o-building-storefront',
            ],
        ];

    @endphp

    <div style="margin-bottom: 1rem; display: flex; justify-content: flex-end;">
        <a href="{{ url('/docs/stripe-settings') }}" target="_blank" rel="noopener"
           style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; border-radius: 0.375rem; background: rgba(56,189,248,0.12); color: rgb(125,211,252); font-size: 0.75rem; font-weight: 500; text-decoration: none;">
            <x-filament::icon icon="heroicon-o-book-open" style="width: 0.875rem; height: 0.875rem;" />
            {{ __('admin/stripe_settings.docs_link') }}
        </a>
    </div>

    @include('filament.pages.partials.settings-shell', [
        'subtitle' => __('admin/stripe_settings.page.subtitle'),
        'badges' => $badges,
        'form' => $this->form,
        'actions' => $this->getFormActions(),
    ])
</x-filament-panels::page>
