<x-filament-panels::page>
    @include('filament.pages.partials.settings-shell', [
        'subtitle' => __('admin.pages.settings.subtitle'),
        'form' => $this->form,
        'actions' => $this->getFormActions(),
    ])
</x-filament-panels::page>
