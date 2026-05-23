/**
 * Base scoped styles for the plugin. Every rule lives under `.ec-root` and
 * uses ONLY Peregrine theme tokens (var(--color-*, --radius-*, …)) so the
 * plugin inherits the host theme automatically — change the accent in Theme
 * Studio and this UI follows. No hardcoded colours/sizes.
 */
export const baseCss = `
.ec-root {
    font-family: var(--font-sans);
    color: var(--color-text-primary);
    font-size: 0.875rem;
    line-height: 1.5;
}
.ec-root *, .ec-root *::before, .ec-root *::after { box-sizing: border-box; }

.ec-stack { display: flex; flex-direction: column; gap: 1.25rem; }
.ec-row { display: flex; align-items: center; gap: 0.625rem; }
.ec-between { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; }
.ec-grow { flex: 1 1 auto; min-width: 0; }
.ec-muted { color: var(--color-text-muted); }
.ec-secondary { color: var(--color-text-secondary); }
.ec-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.ec-title { font-size: 1.125rem; font-weight: 700; margin: 0; line-height: 1.3; }
.ec-subtitle { font-size: 0.8125rem; color: var(--color-text-muted); margin: 0; }
.ec-section-label { font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-text-muted); margin: 0; }

.ec-card {
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    padding: 1rem;
    transition: border-color var(--transition-base), box-shadow var(--transition-base);
}
.ec-card-hover:hover { border-color: var(--color-border-hover); box-shadow: var(--shadow-md); }

.ec-icon-box {
    width: 40px; height: 40px; flex-shrink: 0;
    border-radius: var(--radius-lg);
    background: rgba(var(--color-primary-rgb), 0.1);
    color: var(--color-primary);
    display: flex; align-items: center; justify-content: center;
}

.ec-btn {
    appearance: none; border: none; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
    padding: 0.5rem 0.9rem; font-size: 0.8125rem; font-weight: 600; font-family: inherit;
    border-radius: var(--radius);
    transition: background var(--transition-fast), opacity var(--transition-fast), transform var(--transition-fast), box-shadow var(--transition-fast);
}
.ec-btn:active { transform: scale(0.97); }
.ec-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.ec-btn:focus-visible { outline: none; box-shadow: 0 0 0 2px var(--color-background), 0 0 0 4px var(--color-ring); }
.ec-btn-primary { background: var(--color-primary); color: #fff; box-shadow: 0 2px 8px var(--color-primary-glow); }
.ec-btn-primary:hover:not(:disabled) { background: var(--color-primary-hover); box-shadow: 0 0 20px var(--color-primary-glow); }
.ec-btn-secondary { background: var(--color-surface); color: var(--color-text-primary); border: 1px solid var(--color-border-hover); }
.ec-btn-secondary:hover:not(:disabled) { background: var(--color-surface-hover); border-color: var(--color-text-secondary); }
.ec-btn-ghost { background: transparent; color: var(--color-text-secondary); font-weight: 500; }
.ec-btn-ghost:hover:not(:disabled) { background: var(--color-surface-hover); color: var(--color-text-primary); }
.ec-btn-danger { background: rgba(var(--color-danger-rgb), 0.12); color: var(--color-danger); border: 1px solid rgba(var(--color-danger-rgb), 0.2); }
.ec-btn-danger:hover:not(:disabled) { background: rgba(var(--color-danger-rgb), 0.2); }
.ec-btn-sm { padding: 0.35rem 0.6rem; font-size: 0.75rem; }
.ec-btn-icon { padding: 0; width: 32px; height: 32px; background: transparent; color: var(--color-text-secondary); border: 1px solid var(--color-border); }
.ec-btn-icon:hover:not(:disabled) { background: var(--color-surface-hover); color: var(--color-text-primary); }

.ec-input, .ec-select, .ec-textarea {
    width: 100%; font-family: inherit; font-size: 0.8125rem; color: var(--color-text-primary);
    background: var(--color-background); border: 1px solid var(--color-border);
    border-radius: var(--radius); padding: 0.5rem 0.7rem; outline: none;
    transition: border-color var(--transition-fast), box-shadow var(--transition-fast);
}
.ec-textarea { resize: vertical; min-height: 4.5rem; line-height: 1.45; }
.ec-input:focus, .ec-select:focus, .ec-textarea:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px var(--color-primary-glow); }
.ec-input-invalid { border-color: var(--color-danger); }
.ec-select { cursor: pointer; appearance: none; padding-right: 1.9rem;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238b849e' stroke-width='2'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat; background-position: right 0.6rem center; }

.ec-badge { display: inline-flex; align-items: center; gap: 0.25rem; border-radius: var(--radius-full);
    padding: 0.1rem 0.55rem; font-size: 0.6875rem; font-weight: 600; line-height: 1.4; white-space: nowrap; }
.ec-badge-accent { background: rgba(var(--color-accent-rgb), 0.15); color: var(--color-accent); }
.ec-badge-info { background: rgba(var(--color-info-rgb), 0.15); color: var(--color-info); }
.ec-badge-warning { background: rgba(var(--color-warning-rgb), 0.15); color: var(--color-warning); }
.ec-badge-success { background: rgba(var(--color-success-rgb), 0.15); color: var(--color-success); }
.ec-badge-muted { background: var(--surface-overlay-soft); color: var(--color-text-secondary); }

.ec-callout { display: flex; align-items: flex-start; gap: 0.6rem; padding: 0.7rem 0.85rem; border-radius: var(--radius);
    font-size: 0.8125rem; line-height: 1.45; color: var(--color-text-primary); }
.ec-callout-icon { flex-shrink: 0; margin-top: 0.05rem; }
.ec-callout-warning { background: rgba(var(--color-warning-rgb), 0.12); border: 1px solid rgba(var(--color-warning-rgb), 0.35); border-left-width: 3px; }
.ec-callout-warning .ec-callout-icon { color: var(--color-warning); }
.ec-callout-info { background: rgba(var(--color-info-rgb), 0.1); border: 1px solid rgba(var(--color-info-rgb), 0.3); border-left-width: 3px; }
.ec-callout-info .ec-callout-icon { color: var(--color-info); }

.ec-spinner { width: 1rem; height: 1rem; border-radius: var(--radius-full);
    border: 2px solid var(--surface-overlay-strong); border-top-color: var(--color-primary);
    animation: ec-spin 0.7s linear infinite; display: inline-block; }
.ec-spinner-lg { width: 1.75rem; height: 1.75rem; border-width: 3px; }

.ec-tabs { display: flex; gap: 0.25rem; border-bottom: 1px solid var(--color-border); }
.ec-tab { appearance: none; border: none; background: transparent; cursor: pointer; font-family: inherit;
    padding: 0.5rem 0.85rem; font-size: 0.8125rem; font-weight: 500; color: var(--color-text-secondary);
    border-bottom: 2px solid transparent; margin-bottom: -1px; transition: color var(--transition-fast), border-color var(--transition-fast); }
.ec-tab:hover { color: var(--color-text-primary); }
.ec-tab-active { color: var(--color-primary); border-bottom-color: var(--color-primary); }

.ec-empty { padding: 2.5rem 1rem; text-align: center; color: var(--color-text-muted); font-size: 0.8125rem; }

.ec-root input[type="checkbox"] { accent-color: var(--color-primary); cursor: pointer; width: 1rem; height: 1rem; flex-shrink: 0; }
.ec-check-row { cursor: pointer; }
.ec-check-row:hover { background: var(--color-surface-hover); }

@keyframes ec-spin { to { transform: rotate(360deg); } }
@keyframes ec-fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes ec-pop-in { from { opacity: 0; transform: translateY(6px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
@keyframes ec-slide-up { from { opacity: 0; transform: translate(-50%, 1rem); } to { opacity: 1; transform: translate(-50%, 0); } }
`;
