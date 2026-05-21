{{-- Global admin-table scroll. Injected via PanelsRenderHook::STYLES_AFTER in
     AdminPanelProvider.

     Filament v5 puts the horizontal scroll on `.fi-ta-content-ctn`
     (overflow-x:auto by default). A wide table was still being squeezed to the
     container width (cells cut, no scrollbar), so we force the table itself to
     its natural width (min-width:max-content): it then overflows the container
     and the existing overflow-x kicks in → real horizontal scrollbar. We also
     cap the container height for vertical scrolling on long lists. --}}
<style>
    .fi-ta-content-ctn {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 75vh;
    }

    .fi-ta-content-ctn .fi-ta-table {
        min-width: max-content;
    }
</style>
