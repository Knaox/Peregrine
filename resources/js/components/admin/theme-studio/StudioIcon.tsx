/**
 * Inline stroke-icon helper + the path data used in the Theme Studio header.
 * Extracted from ThemeStudioPage so that page stays under the 300-line cap.
 */
export const StudioIcon = ({ d, size = 14 }: { d: string; size?: number }) => (
    <svg
        width={size}
        height={size}
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth={2}
        strokeLinecap="round"
        strokeLinejoin="round"
    >
        <path d={d} />
    </svg>
);

export const ARROW_LEFT = 'M19 12H5 M12 19l-7-7 7-7';
export const SAVE_ICON = 'M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z M17 21v-8H7v8 M7 3v5h8';
export const RESET_ICON = 'M3 12a9 9 0 1015-7 M3 4v5h5';
export const UNDO_ICON = 'M3 7v6h6 M21 17a9 9 0 00-15-6.7L3 13';
