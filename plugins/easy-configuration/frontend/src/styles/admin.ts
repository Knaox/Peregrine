/** Admin template-manager + shared layout helpers. Tokens only. */
export const adminCss = `
.ec-page { max-width: var(--layout-container-max, 1100px); margin: 0 auto; padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.ec-grid { display: grid; gap: 0.85rem; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
.ec-list { display: flex; flex-direction: column; gap: 0.5rem; }
.ec-egg-list { display: flex; flex-direction: column; gap: 0.4rem; max-height: 18rem; overflow-y: auto; padding-right: 0.25rem; }
.ec-mono { font-family: var(--font-mono); font-size: 0.78rem; line-height: 1.5; min-height: 22rem; white-space: pre; tab-size: 2; }
.ec-field-group { display: flex; flex-direction: column; gap: 0.35rem; }
.ec-field-group > label { font-size: 0.75rem; font-weight: 600; color: var(--color-text-secondary); }
.ec-cols-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.ec-divider { height: 1px; background: var(--color-border); border: none; margin: 0.25rem 0; }
.ec-error-list { margin: 0; padding-left: 1.1rem; color: var(--color-danger); font-size: 0.78rem; display: flex; flex-direction: column; gap: 0.2rem; }
.ec-template-card { display: flex; flex-direction: column; gap: 0.6rem; }
.ec-template-card-foot { display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap; }
/* Action row: up to 4 actions (edit / import egg / export / delete) on a
   280px-min card — wrap instead of overflowing past the card edge. */
.ec-template-card-actions { display: flex; align-items: center; gap: 0.35rem; row-gap: 0.4rem; flex-wrap: wrap; }
.ec-template-card-actions .ec-btn { white-space: nowrap; }
.ec-pathbar { flex-wrap: wrap; gap: 0.2rem; padding: 0.1rem 0; }
.ec-crumb { background: none; border: none; color: var(--color-text-secondary); cursor: pointer; font-size: 0.8rem; padding: 0.1rem 0.3rem; border-radius: var(--radius); max-width: 12rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ec-crumb:hover { color: var(--color-text); background: var(--color-surface-elevated); }
.ec-link-group { display: flex; flex-direction: column; gap: 0.5rem; padding: 0.4rem 0 0.5rem 0.75rem; border-left: 2px solid var(--color-border); }
.ec-link-key { font-size: 0.78rem; font-weight: 500; color: var(--color-text-secondary); word-break: break-word; }
.ec-section-row { display: grid; grid-template-columns: minmax(7rem, 1fr) 1fr 1fr; gap: 0.5rem; align-items: center; }
@media (max-width: 640px) { .ec-cols-2 { grid-template-columns: 1fr; } .ec-section-row { grid-template-columns: 1fr; } }
`;
