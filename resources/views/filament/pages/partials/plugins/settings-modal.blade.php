{{--
    Plugin settings modal. Renders only when $settingsPluginId is set —
    the form schema is built dynamically by the controller from the
    plugin's manifest.settings_schema.

    Variables :
      - $settingsPluginId : ?string
      - $plugins          : array — used to look up the plugin's name/logo
                            so the modal header carries identity.
--}}
@php
    $settingsPlugin = collect($plugins)->firstWhere('id', $settingsPluginId) ?? [];
@endphp
<x-filament::modal id="plugin-settings" width="lg">
    <x-slot name="heading">
        <div style="display: flex; align-items: center; gap: 0.875rem;">
            @include('filament.pages.partials.plugin-logo', [
                'official' => $settingsPlugin['official'] ?? false,
                'iconUrl' => $settingsPlugin['icon_url'] ?? null,
            ])
            <div style="min-width: 0;">
                <div style="font-size: 1rem; font-weight: 600; color: rgba(255,255,255,0.78); line-height: 1.2;">
                    {{ $settingsPlugin['name'] ?? $settingsPluginId }}
                </div>
                <div style="font-size: 0.75rem; color: rgba(255,255,255,0.5); margin-top: 0.125rem;">
                    {{ __('admin/plugins.settings_modal_subtitle') }}
                </div>
            </div>
        </div>
    </x-slot>

    <form wire:submit="saveSettings">
        {{ $this->form }}

        <div style="margin-top: 1.25rem; display: flex; justify-content: flex-end; gap: 0.5rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.06);">
            <x-filament::button type="button" color="gray" x-on:click="$dispatch('close-modal', { id: 'plugin-settings' })">
                {{ __('admin/_shell.common.cancel') }}
            </x-filament::button>
            <x-filament::button type="submit" icon="heroicon-m-check">
                {{ __('admin/_shell.common.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament::modal>
