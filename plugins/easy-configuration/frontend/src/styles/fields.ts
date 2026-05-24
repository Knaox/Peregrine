/**
 * Field + section styles — the Nitrado-inspired editor surface. Spacious one
 * card per parameter, label left, control right, dirty/saved indicators.
 * Tokens only.
 */
export const fieldsCss = `
/* File-level collapse toggle: the file title doubles as the open/close control. */
.ec-file-head { display: flex; align-items: center; gap: 0.5rem; width: 100%; padding: 0; background: transparent;
    border: none; cursor: pointer; font-family: inherit; color: var(--color-text-primary); text-align: left; }
.ec-file-head:hover .ec-title { color: var(--color-accent); }
.ec-file-head:disabled { cursor: default; }
.ec-file-head:disabled:hover .ec-title { color: var(--color-text-primary); }

.ec-section-group { border: 1px solid var(--color-border); border-radius: var(--radius-lg); overflow: hidden; background: var(--color-surface); }
.ec-section-head { display: flex; align-items: center; gap: 0.5rem; width: 100%; padding: 0.75rem 1rem;
    background: transparent; border: none; cursor: pointer; font-family: inherit; color: var(--color-text-primary);
    font-size: 0.8125rem; font-weight: 600; text-align: left; }
.ec-section-head:hover { background: var(--color-surface-hover); }
.ec-section-chevron { transition: transform var(--transition-base); color: var(--color-text-muted); display: inline-flex; }
.ec-section-collapsed .ec-section-chevron { transform: rotate(-90deg); }
.ec-section-body { display: flex; flex-direction: column; }
.ec-section-count { margin-left: auto; font-size: 0.6875rem; color: var(--color-text-muted); font-weight: 500; }

/* Multi-column layouts (admin-chosen, per template): each parameter becomes a
   self-contained card, label stacked over control. Collapses to one column on
   narrow screens so nothing is cramped. */
.ec-section-cols-2, .ec-section-cols-3 { display: grid; gap: 0.6rem; padding: 0.6rem; }
.ec-section-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.ec-section-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.ec-section-cols-2 .ec-field, .ec-section-cols-3 .ec-field { flex-direction: column; align-items: stretch; gap: 0.5rem;
    border: 1px solid var(--color-border); border-radius: var(--radius); padding: 0.75rem; }
.ec-section-cols-2 .ec-field-control, .ec-section-cols-3 .ec-field-control { justify-content: flex-start; flex-basis: auto; }
@media (max-width: 720px) { .ec-section-cols-2, .ec-section-cols-3 { grid-template-columns: 1fr; } }

.ec-field { display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1rem; border-top: 1px solid var(--color-border); position: relative; }
.ec-field:first-child { border-top: none; }
.ec-field-dirty { box-shadow: inset 3px 0 0 var(--color-primary); }
.ec-field-label-col { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; flex: 1 1 50%; }
.ec-field-label { font-weight: 500; display: inline-flex; align-items: center; gap: 0.4rem; }
.ec-field-desc { font-size: 0.75rem; color: var(--color-text-muted); }
.ec-field-control { flex: 1 1 45%; display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem; min-width: 0; }
.ec-field-value { color: var(--color-accent); font-weight: 600; font-variant-numeric: tabular-nums; }
.ec-field-inferred { font-style: italic; }
.ec-field-saved { color: var(--color-success); display: inline-flex; animation: ec-fade-in var(--transition-base); }

.ec-help { color: var(--color-text-muted); display: inline-flex; cursor: help; }

.ec-reset { opacity: 0; transition: opacity var(--transition-fast); }
.ec-field:hover .ec-reset { opacity: 1; }

/* Toggle */
.ec-toggle { position: relative; width: 2.5rem; height: 1.4rem; border-radius: var(--radius-full); border: none; cursor: pointer;
    background: var(--color-border-hover); transition: background var(--transition-base); flex-shrink: 0; }
.ec-toggle-on { background: var(--color-primary); }
.ec-toggle-knob { position: absolute; top: 2px; left: 2px; width: calc(1.4rem - 4px); height: calc(1.4rem - 4px);
    border-radius: var(--radius-full); background: #fff; transition: transform var(--transition-base); }
.ec-toggle-on .ec-toggle-knob { transform: translateX(1.1rem); }
.ec-toggle:disabled { opacity: 0.5; cursor: not-allowed; }

/* Slider */
.ec-slider-wrap { display: flex; align-items: center; gap: 0.75rem; width: 100%; }
.ec-slider { -webkit-appearance: none; appearance: none; flex: 1 1 auto; height: 0.35rem; border-radius: var(--radius-full);
    background: var(--color-border-hover); outline: none; cursor: pointer; }
.ec-slider::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 1rem; height: 1rem; border-radius: var(--radius-full);
    background: var(--color-primary); border: 2px solid var(--color-surface); box-shadow: var(--shadow-sm); cursor: pointer; }
.ec-slider::-moz-range-thumb { width: 1rem; height: 1rem; border-radius: var(--radius-full); background: var(--color-primary);
    border: 2px solid var(--color-surface); cursor: pointer; }
.ec-slider-number { width: 5rem; flex-shrink: 0; text-align: right; }
.ec-input-narrow { max-width: 8rem; }

/* Boost selection ×/÷ segmented toggle (multiply vs divide a ticked parameter). */
.ec-seg { display: inline-flex; border: 1px solid var(--color-border); border-radius: var(--radius); overflow: hidden; flex-shrink: 0; }
.ec-seg-btn { appearance: none; border: none; cursor: pointer; font-family: inherit; font-size: 0.75rem; font-weight: 600;
    padding: 0.2rem 0.5rem; background: var(--color-surface); color: var(--color-text-secondary);
    transition: background var(--transition-fast), color var(--transition-fast); }
.ec-seg-btn + .ec-seg-btn { border-left: 1px solid var(--color-border); }
.ec-seg-btn:hover { background: var(--color-surface-hover); color: var(--color-text-primary); }
.ec-seg-on, .ec-seg-on:hover { background: var(--color-primary); color: #fff; }

/* Color */
.ec-color { display: flex; align-items: center; gap: 0.5rem; }
.ec-color-swatch { width: 1.6rem; height: 1.6rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); padding: 0; cursor: pointer; background: none; }

/* Search */
.ec-search { position: relative; }
.ec-search .ec-input { padding-left: 2rem; }
.ec-search-icon { position: absolute; left: 0.6rem; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); pointer-events: none; }

/* Multiselect chips */
.ec-chips { display: flex; flex-wrap: wrap; gap: 0.35rem; justify-content: flex-end; }
.ec-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.5rem; border-radius: var(--radius-full);
    font-size: 0.7rem; cursor: pointer; border: 1px solid var(--color-border); background: var(--color-background); color: var(--color-text-secondary); transition: all var(--transition-fast); }
.ec-chip-on { background: rgba(var(--color-primary-rgb), 0.15); border-color: var(--color-primary); color: var(--color-primary); }
`;
