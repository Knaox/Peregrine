<x-filament-panels::page>
    @include('peregrine-player-counter::partials.supported-games')

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit">
                {{ __('peregrine-player-counter::messages.settings.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
