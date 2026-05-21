{{-- Global admin-table scroll. Injected via PanelsRenderHook::STYLES_AFTER in
     AdminPanelProvider so every Filament table scrolls instead of being clipped.
     - Vertical: the content wrapper caps its height and scrolls.
     - Horizontal: forcing the table to its natural (max-content) width stops
       columns from being squeezed to fit, so a wide table overflows the
       wrapper and shows a horizontal scrollbar instead of cutting cells. --}}
<style>
    .fi-ta-content {
        overflow: auto;
        max-height: 75vh;
    }

    .fi-ta-content > table,
    .fi-ta-content table.fi-ta-table {
        min-width: max-content;
    }
</style>
