{{-- Global admin-table scroll. Injected via PanelsRenderHook::STYLES_AFTER
     (rendered in Filament's <head>, base.blade.php).

     Filament v5 clips horizontal overflow on .fi-layout (overflow-x:clip) and
     lays the page out with flexbox. Flex items default to min-width:auto, so
     they refuse to shrink below their content width: a wide table therefore
     widens the whole layout (then gets clipped) instead of scrolling inside
     .fi-ta-content-ctn (which already has overflow-x:auto).

     Fix (the standard flexbox overflow remedy): force min-width:0 on every
     wrapper in the chain so the scroll container is bounded to the available
     width and its overflow-x finally produces a scrollbar. Keep the table at
     its natural width so it actually overflows. !important defeats Filament's
     own utility rules. Height is capped for vertical scroll on long lists. --}}
<style>
    .fi-main-ctn,
    .fi-main,
    .fi-page,
    .fi-ta-ctn,
    .fi-ta-content-ctn {
        min-width: 0 !important;
    }

    .fi-ta-content-ctn {
        overflow: auto !important;
        max-height: 75vh;
    }

    .fi-ta-content-ctn .fi-ta-table {
        min-width: max-content;
    }
</style>
