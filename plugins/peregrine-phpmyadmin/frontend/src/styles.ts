let injected = false;

/**
 * Inject the scoped button stylesheet once. Uses the host's CSS custom
 * properties (with fallbacks) so the button matches the surrounding
 * secondary actions in the database row.
 */
export function injectStyles(): void {
    if (injected || typeof document === 'undefined') {
        return;
    }
    injected = true;

    const style = document.createElement('style');
    style.id = 'pma-plugin-styles';
    style.textContent = `
.pma-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: var(--radius, 0.5rem);
    border: 1px solid var(--color-border, rgba(255,255,255,0.12));
    background: var(--color-surface-hover, rgba(255,255,255,0.05));
    color: var(--color-text-primary, #f8fafc);
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    transition: border-color .15s ease, background .15s ease;
}
.pma-btn:hover:not(:disabled) { border-color: var(--color-primary, #f97316); }
.pma-btn:disabled { opacity: .5; cursor: not-allowed; }
.pma-btn svg { width: 0.95rem; height: 0.95rem; flex: none; }
`;
    document.head.appendChild(style);
}
