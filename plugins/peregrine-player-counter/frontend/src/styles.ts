const STYLE_ID = 'pgpc-styles';

/**
 * Inject the keyframes the card needs (a CSS animation can't be inline). Scoped
 * to a `pgpc-` prefix and idempotent; everything else is inline + CSS vars so
 * the bundle carries no Tailwind/utility dependency.
 */
export function injectStyles(): void {
    if (typeof document === 'undefined' || document.getElementById(STYLE_ID)) {
        return;
    }

    const el = document.createElement('style');
    el.id = STYLE_ID;
    el.textContent = [
        '@keyframes pgpc-ping { 75%, 100% { transform: scale(2); opacity: 0; } }',
        '.pgpc-ping { animation: pgpc-ping 1.4s cubic-bezier(0,0,0.2,1) infinite; }',
        '@media (prefers-reduced-motion: reduce) { .pgpc-ping { animation: none !important; } }',
    ].join('\n');
    document.head.appendChild(el);
}
