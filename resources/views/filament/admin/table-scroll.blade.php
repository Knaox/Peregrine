{{-- Global admin-table scroll. Injected via PanelsRenderHook::STYLES_AFTER in
     AdminPanelProvider so every Filament table scrolls instead of being clipped
     when it has many columns (horizontal) or many rows (vertical). Scoped to
     Filament's table content wrapper (.fi-ta-content) so nothing else moves. --}}
<style>
    .fi-ta-content {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 75vh;
    }
</style>
