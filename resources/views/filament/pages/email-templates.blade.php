<x-filament-panels::page>
    @php
        $bridgeActive = app(\App\Services\Bridge\BridgeModeService::class)->isShopStripe();
        $badges = [];
        if ($bridgeActive) {
            $badges[] = ['label' => __('admin/_shell.badges.bridge_mode.shop_stripe'), 'color' => 'success', 'icon' => 'heroicon-o-link'];
        }
    @endphp

    @include('filament.pages.partials.settings-shell', [
        'subtitle' => __('admin/email_templates.page.subtitle'),
        'badges' => $badges,
        'form' => $this->form,
        'actions' => $this->getFormActions(),
    ])
</x-filament-panels::page>
