/**
 * Reusable permission picker: expandable groups of checkboxes.
 * Used by invite modal and both edit modals.
 */
import { C, h, PG } from './shared';

interface Args {
    groups: PG[];
    selected: Set<string>;
    expanded: Set<string>;
    onToggle: (key: string) => void;
    onToggleAll: (group: PG) => void;
    onToggleExpand: (key: string) => void;
    emptyLabel: string;
    /** Optional key for placement inside a parent array. */
    key?: string;
}

export function renderPermissionPicker(args: Args): ReturnType<typeof h> {
    const { groups, selected, expanded, onToggle, onToggleAll, onToggleExpand, emptyLabel, key } = args;

    if (groups.length === 0) {
        return h('div', {
            key,
            style: { padding: '2rem 1rem', textAlign: 'center' as const, color: 'var(--color-text-muted)', fontSize: '0.8125rem' },
        }, emptyLabel);
    }

    return h('div', {
        key,
        style: { maxHeight: '45vh', overflowY: 'auto' as const, borderRadius: 'var(--radius)', border: '1px solid var(--color-border)', background: 'var(--color-background)' },
    }, groups.map(g => {
        const ks = g.permissions.map(p => p.key);
        const allSelected = ks.every(k => selected.has(k));
        const someSelected = ks.some(k => selected.has(k));
        const isExpanded = expanded.has(g.group);
        const count = ks.filter(k => selected.has(k)).length;

        return h('div', { key: g.group, style: { borderBottom: '1px solid var(--color-border)' } }, [
            h('div', {
                key: 'h',
                onClick: () => onToggleExpand(g.group),
                style: { display: 'flex', alignItems: 'center', gap: '0.625rem', padding: '0.75rem 1rem', cursor: 'pointer', transition: 'background 150ms' },
                onMouseEnter: (e: React.MouseEvent<HTMLDivElement>) => { (e.currentTarget as HTMLElement).style.background = 'var(--color-surface-hover)'; },
                onMouseLeave: (e: React.MouseEvent<HTMLDivElement>) => { (e.currentTarget as HTMLElement).style.background = 'transparent'; },
            }, [
                h('input', {
                    key: 'c', type: 'checkbox', checked: allSelected,
                    ref: (el: HTMLInputElement | null) => { if (el) el.indeterminate = someSelected && !allSelected; },
                    onChange: () => onToggleAll(g),
                    onClick: (e: React.MouseEvent) => e.stopPropagation(),
                    style: { width: 16, height: 16, cursor: 'pointer', accentColor: 'var(--color-primary)' },
                }),
                h('span', { key: 'l', style: { flex: 1, fontSize: '0.875rem', fontWeight: 500, color: 'var(--color-text-primary)' } }, g.label),
                count > 0
                    ? h('span', { key: 'n', style: C.permBadge }, `${count}/${ks.length}`)
                    : h('span', { key: 'n', style: { fontSize: '0.6875rem', color: 'var(--color-text-muted)' } }, `0/${ks.length}`),
                h('svg', {
                    key: 'v', width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none',
                    stroke: 'var(--color-text-muted)', strokeWidth: 2,
                    style: { transition: 'transform 200ms', transform: isExpanded ? 'rotate(180deg)' : '' },
                }, h('path', { d: 'M19 9l-7 7-7-7' })),
            ]),
            isExpanded
                ? h('div', { key: 'b', style: { padding: '0 0.5rem 0.75rem 2.75rem' } },
                    g.permissions.map(p => h('label', {
                        key: p.key,
                        style: {
                            display: 'flex', alignItems: 'center', gap: '0.5rem',
                            padding: '0.375rem 0.5rem', fontSize: '0.8125rem',
                            color: selected.has(p.key) ? 'var(--color-text-primary)' : 'var(--color-text-secondary)',
                            cursor: 'pointer', borderRadius: 'var(--radius-sm)', transition: 'color 150ms',
                        },
                    }, [
                        h('input', {
                            key: 'c', type: 'checkbox',
                            checked: selected.has(p.key), onChange: () => onToggle(p.key),
                            style: { width: 16, height: 16, cursor: 'pointer', accentColor: 'var(--color-primary)' },
                        }),
                        h('span', { key: 'l' }, p.label),
                    ])))
                : null,
        ]);
    }));
}
