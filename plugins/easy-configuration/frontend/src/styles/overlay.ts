/**
 * Overlay surfaces — dialogs, the floating save bar, the running overlay, toasts
 * and tooltips. Glass effects reuse the host's `--color-glass` / `--glass-blur`
 * tokens so they match core surfaces exactly.
 */
export const overlayCss = `
.ec-scrim { position: fixed; inset: 0; z-index: 60; background: var(--modal-scrim);
    backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem;
    animation: ec-fade-in var(--transition-fast); }
.ec-dialog { width: 100%; max-width: 560px; max-height: calc(100vh - 2rem); overflow-y: auto;
    border-radius: var(--radius-lg); border: 1px solid var(--color-border); background: var(--color-surface);
    box-shadow: var(--shadow-lg); display: flex; flex-direction: column; animation: ec-pop-in var(--transition-base); }
.ec-dialog-lg { max-width: 760px; }
/* File-editor-style full-viewport overlay: fills the scrim (which keeps a
   responsive inset), fixed head/foot, the body scrolls on its own. */
.ec-dialog-xl { max-width: none; width: 100%; height: 100%; max-height: 100%; overflow: hidden; }
@media (min-width: 768px) { .ec-scrim:has(.ec-dialog-xl) { padding: 2rem; } }
@media (min-width: 1024px) { .ec-scrim:has(.ec-dialog-xl) { padding: 3rem 4rem; } }
.ec-dialog-xl .ec-dialog-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; }
.ec-dialog-head { display: flex; align-items: center; gap: 0.6rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--color-border); }
.ec-dialog-title { font-size: 0.95rem; font-weight: 700; margin: 0; }
.ec-dialog-body { padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; }
.ec-dialog-foot { display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;
    padding: 1rem 1.25rem; border-top: 1px solid var(--color-border); }

.ec-steps { display: flex; align-items: center; gap: 0.4rem; }
.ec-step-dot { width: 1.5rem; height: 1.5rem; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 600; background: var(--surface-overlay-soft); color: var(--color-text-muted); }
.ec-step-dot-active { background: var(--color-primary); color: #fff; }
.ec-step-bar { flex: 1; height: 2px; background: var(--color-border); }

.ec-save-bar { position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%); z-index: 50;
    display: flex; align-items: center; gap: 0.85rem; padding: 0.6rem 0.7rem 0.6rem 1.1rem;
    border-radius: var(--radius-full); border: 1px solid var(--color-glass-border);
    background: var(--color-glass); backdrop-filter: var(--glass-blur); box-shadow: var(--shadow-lg);
    animation: ec-slide-up var(--transition-smooth); }
.ec-save-bar-text { font-size: 0.8125rem; font-weight: 500; }

.ec-overlay { position: absolute; inset: 0; z-index: 20; border-radius: var(--radius-lg);
    background: var(--ambient-overlay); backdrop-filter: blur(2px);
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.85rem; text-align: center; padding: 1.5rem; }
.ec-overlay-card { display: flex; flex-direction: column; align-items: center; gap: 0.85rem; max-width: 22rem; }
.ec-relative { position: relative; }

/* Read-only notice shown above the editor while the server runs (non-blocking). */
.ec-banner { display: flex; align-items: center; gap: 0.85rem; padding: 0.85rem 1rem; border-radius: var(--radius-lg);
    border: 1px solid var(--color-border); background: var(--color-surface); }
.ec-banner-icon { color: var(--color-warning); display: inline-flex; flex-shrink: 0; }
.ec-banner-body { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 0.1rem; }
.ec-banner-title { font-weight: 600; }

.ec-toast-host { position: fixed; bottom: 1.5rem; right: 1.5rem; z-index: 80; display: flex; flex-direction: column; gap: 0.5rem; max-width: 22rem; }
.ec-toast { display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.7rem 0.85rem; border-radius: var(--radius);
    border: 1px solid var(--color-border); background: var(--color-glass); backdrop-filter: var(--glass-blur);
    box-shadow: var(--shadow-md); font-size: 0.8125rem; animation: ec-pop-in var(--transition-base); }
.ec-toast-error { border-color: rgba(var(--color-danger-rgb), 0.4); }
.ec-toast-success { border-color: rgba(var(--color-success-rgb), 0.4); }
.ec-toast-icon { flex-shrink: 0; margin-top: 0.05rem; }
.ec-toast-error .ec-toast-icon { color: var(--color-danger); }
.ec-toast-success .ec-toast-icon { color: var(--color-success); }
.ec-toast-warning .ec-toast-icon { color: var(--color-warning); }
.ec-toast-body { min-width: 0; }
.ec-toast-detail { display: block; margin-top: 0.3rem; padding: 0.25rem 0.4rem; border-radius: calc(var(--radius) / 2);
    background: rgba(var(--color-primary-rgb), 0.1); border: 1px solid rgba(var(--color-primary-rgb), 0.25);
    font-family: var(--font-mono, monospace); font-size: 0.68rem; letter-spacing: 0.02em; word-break: break-all; }

.ec-tooltip { position: relative; display: inline-flex; }
.ec-tooltip-pop { position: absolute; bottom: calc(100% + 0.4rem); left: 50%; transform: translateX(-50%);
    background: var(--color-surface-elevated); color: var(--color-text-primary); border: 1px solid var(--color-border);
    border-radius: var(--radius-sm); padding: 0.4rem 0.6rem; font-size: 0.7rem; font-weight: 400; white-space: normal; width: max-content; max-width: 14rem;
    box-shadow: var(--shadow-md); z-index: 70; opacity: 0; pointer-events: none; transition: opacity var(--transition-fast); }
/* Anchor to the trigger's left/right edge instead of centring — keeps the popup
   inside narrow column cards (where a centred popup overflows and gets clipped
   by the section group's overflow:hidden). */
.ec-tooltip-pop-start { left: 0; right: auto; transform: none; }
.ec-tooltip-pop-end { left: auto; right: 0; transform: none; }
.ec-tooltip:hover .ec-tooltip-pop, .ec-tooltip:focus-within .ec-tooltip-pop { opacity: 1; }

.ec-server-row { display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem; border-radius: var(--radius);
    border: 1px solid var(--color-border); cursor: pointer; transition: all var(--transition-fast); background: var(--color-background); }
.ec-server-row:hover:not(.ec-server-row-disabled) { border-color: var(--color-border-hover); }
.ec-server-row-on { border-color: var(--color-primary); background: rgba(var(--color-primary-rgb), 0.08); }
.ec-server-row-disabled { opacity: 0.5; cursor: not-allowed; }
.ec-server-thumb { width: 2.5rem; height: 2.5rem; border-radius: var(--radius); object-fit: cover; flex-shrink: 0; background: var(--color-surface-elevated); }
`;
