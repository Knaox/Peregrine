/**
 * Tiny color helpers used by the Theme Studio's live preview to derive
 * the same RGB triplets / glow rgba() values that PHP's CssVariableBuilder
 * computes server-side. Keeping the math in two places is acceptable given
 * how stable the formulas are — and avoids a network round-trip per
 * keystroke when the user drags a color picker.
 */

function normalizeHex(hex: string): string {
    let value = (hex || '').trim();
    if (value.startsWith('#')) value = value.slice(1);
    if (value.length === 3) {
        value = value.split('').map((c) => c + c).join('');
    }
    if (value.length === 8) {
        // Drop the alpha channel — CSS vars carry alpha via rgba() helpers.
        value = value.slice(0, 6);
    }
    if (!/^[0-9a-fA-F]{6}$/.test(value)) {
        return '000000';
    }
    return value.toLowerCase();
}

export function hexToRgb(hex: string): { r: number; g: number; b: number } {
    const v = normalizeHex(hex);
    return {
        r: parseInt(v.slice(0, 2), 16),
        g: parseInt(v.slice(2, 4), 16),
        b: parseInt(v.slice(4, 6), 16),
    };
}

export function hexToRgbTriplet(hex: string): string {
    const { r, g, b } = hexToRgb(hex);
    return `${r}, ${g}, ${b}`;
}

export function hexToRgba(hex: string, alpha: number): string {
    const { r, g, b } = hexToRgb(hex);
    const clamped = Math.max(0, Math.min(1, alpha));
    return `rgba(${r}, ${g}, ${b}, ${clamped})`;
}

/**
 * Relative luminance per WCAG 2.x — used by accessibility checks (later
 * vague) and by the preview's auto-text-color fallback.
 */
export function luminance(hex: string): number {
    const { r, g, b } = hexToRgb(hex);
    const transform = (channel: number): number => {
        const c = channel / 255;
        return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * transform(r) + 0.7152 * transform(g) + 0.0722 * transform(b);
}

export function contrastRatio(a: string, b: string): number {
    const la = luminance(a);
    const lb = luminance(b);
    const lighter = Math.max(la, lb);
    const darker = Math.min(la, lb);
    return (lighter + 0.05) / (darker + 0.05);
}
