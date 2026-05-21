{{-- Global admin-table scroll. Injected via PanelsRenderHook::STYLES_AFTER.

     Filament v5 clips horizontal overflow on .fi-layout (overflow-x:clip) and
     builds the table area with flexbox (.fi-ta-ctn{display:flex}). A flex item
     defaults to min-width:auto, so it refuses to shrink below its content: a
     wide table therefore widens the whole layout (and gets clipped) instead of
     scrolling inside .fi-ta-content-ctn (which already has overflow-x:auto).

     Fix: allow the flex chain to shrink (min-width:0) so the scroll container
     is constrained to the available width and its overflow-x can kick in; keep
     the table at its natural width so it actually overflows. Cap the height for
     vertical scroll on long lists. --}}
<style>
    .fi-main,
    .fi-ta-ctn,
    .fi-ta-content-ctn {
        min-width: 0;
    }

    .fi-ta-content-ctn {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 75vh;
    }

    .fi-ta-content-ctn .fi-ta-table {
        min-width: max-content;
    }
</style>
