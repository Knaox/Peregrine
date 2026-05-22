import { adminCss } from './admin';
import { baseCss } from './base';
import { fieldsCss } from './fields';
import { overlayCss } from './overlay';

const STYLE_ID = 'easy-config-styles';

/**
 * Injects the plugin's scoped stylesheet once. The CSS is shipped as strings
 * (not a separate asset) so the single IIFE bundle stays self-contained — the
 * host's plugin loader only fetches `bundle.js`. Every rule is scoped under
 * `.ec-root` and built from theme tokens, so nothing leaks into the host and
 * the theme is inherited automatically.
 */
export function injectStyles(): void {
    if (typeof document === 'undefined' || document.getElementById(STYLE_ID)) {
        return;
    }

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = [baseCss, fieldsCss, overlayCss, adminCss].join('\n');
    document.head.appendChild(style);
}
