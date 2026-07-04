/** Styles for the 7DTD SandboxCode generator field (config.generator = '7dtd-sandbox'). */
export const sandboxCss = `
.ec-root .sbx { display: flex; flex-direction: column; gap: 0.5rem; }
.ec-root .sbx-row { display: flex; align-items: center; gap: 0.4rem; }
.ec-root .sbx-row .ec-input { flex: 1; font-family: var(--font-mono, monospace); font-size: 0.78rem; letter-spacing: 0.02em; }
.ec-root .sbx-error { margin: 0; font-size: 0.78rem; color: var(--color-danger); }
.ec-root .sbx-hint { margin: 0; font-size: 0.75rem; color: var(--color-text-muted); }
.ec-root .sbx-link { background: none; border: none; padding: 0; cursor: pointer; font-size: inherit; color: var(--color-primary); text-decoration: underline; }
.ec-root .sbx-panel { border: 1px solid var(--color-border); border-radius: var(--radius); background: var(--color-surface); padding: 0.6rem 0.75rem; display: flex; flex-direction: column; gap: 0.25rem; }
.ec-root .sbx-toolbar { display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap; padding-bottom: 0.4rem; }
.ec-root .sbx-search { flex: 1; min-width: 160px; }
.ec-root .sbx-count { font-size: 0.75rem; color: var(--color-text-muted); white-space: nowrap; }
.ec-root .sbx-group { border-top: 1px solid var(--color-border); }
.ec-root .sbx-group-head { display: flex; align-items: center; gap: 0.4rem; width: 100%; padding: 0.5rem 0.15rem; background: none; border: none; cursor: pointer; color: var(--color-text-primary); font-size: 0.82rem; font-weight: 600; }
.ec-root .sbx-group-head:hover { color: var(--color-primary); }
.ec-root .sbx-group-count { display: inline-flex; align-items: center; justify-content: center; min-width: 1.15rem; height: 1.15rem; padding: 0 0.3rem; border-radius: var(--radius-full); background: rgba(var(--color-primary-rgb), 0.15); color: var(--color-primary); font-size: 0.68rem; font-weight: 700; }
.ec-root .sbx-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.6rem 0.9rem; padding: 0.25rem 0.15rem 0.7rem; }
.ec-root .sbx-opt { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; }
.ec-root .sbx-opt-head { display: flex; align-items: center; gap: 0.35rem; min-width: 0; }
.ec-root .sbx-opt-label { font-size: 0.76rem; color: var(--color-text-secondary, var(--color-text-muted)); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ec-root .sbx-opt-dot { width: 6px; height: 6px; flex: 0 0 auto; border-radius: 50%; background: var(--color-primary); }
.ec-root .sbx-opt-disabled { opacity: 0.45; pointer-events: none; }
.ec-root .sbx-empty { margin: 0.4rem 0 0; font-size: 0.8rem; color: var(--color-text-muted); }
`;
