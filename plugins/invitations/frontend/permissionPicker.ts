/**
 * Reusable permission picker: a global "select all" header above expandable
 * groups of checkboxes (each group keeps its own select-all + count).
 * Used by the invite modal and both edit modals.
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
    /** Header shown above keys listed in `group.advanced`. */
    advancedLabel?: string;
    /** Global "select all permissions" affordance (spans every group). */
    allSelected?: boolean;
    someSelected?: boolean;
    onToggleAllGlobal?: () => void;
    globalLabel?: string;
    /** Optional key for placement inside a parent array. */
    key?: string;
}

export function renderPermissionPicker(args: Args): ReturnType<typeof h> {
    const { groups, selected, expanded, onToggle, onToggleAll, onToggleExpand, emptyLabel, advancedLabel } = args;
    const { allSelected, someSelected, onToggleAllGlobal, globalLabel, key } = args;

    if (groups.length === 0) {
        return h('div', {
            key,
            style: { padding: '2rem 1rem', textAlign: 'center' as const, color: 'var(--color-text-muted)', fontSize: '0.8125rem', border: '1px solid var(--color-border)', borderRadius: 'var(--radius)' },
        }, emptyLabel);
    }

    const renderGroup = (g: PG): ReturnType<typeof h> => {
        const ks = g.permissions.map(p => p.key);
        const allSel = ks.every(k => selected.has(k));
        const someSel = ks.some(k => selected.has(k));
        const isExpanded = expanded.has(g.group);
        const count = ks.filter(k => selected.has(k)).length;
        const advancedSet = new Set(g.advanced ?? []);
        const basicPerms = g.permissions.filter(p => !advancedSet.has(p.key));
        const advancedPerms = g.permissions.filter(p => advancedSet.has(p.key));

        const renderPerm = (p: { key: string; label: string }) => h('label', {
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
                style: C.checkbox,
            }),
            h('span', { key: 'l' }, p.label),
        ]);

        return h('div', { key: g.group, style: { borderBottom: '1px solid var(--color-border)' } }, [
            h('div', {
                key: 'h',
                onClick: () => onToggleExpand(g.group),
                style: { display: 'flex', alignItems: 'center', gap: '0.625rem', padding: '0.75rem 1rem', cursor: 'pointer', transition: 'background 150ms' },
                onMouseEnter: (e: React.MouseEvent<HTMLDivElement>) => { (e.currentTarget as HTMLElement).style.background = 'var(--color-surface-hover)'; },
                onMouseLeave: (e: React.MouseEvent<HTMLDivElement>) => { (e.currentTarget as HTMLElement).style.background = 'transparent'; },
            }, [
                h('input', {
                    key: 'c', type: 'checkbox', checked: allSel,
                    ref: (el: HTMLInputElement | null) => { if (el) el.indeterminate = someSel && !allSel; },
                    onChange: () => onToggleAll(g),
                    onClick: (e: React.MouseEvent) => e.stopPropagation(),
                    style: C.checkbox,
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
                ? h('div', { key: 'b', style: { padding: '0 0.5rem 0.75rem 2.75rem' } }, [
                    ...basicPerms.map(renderPerm),
                    advancedPerms.length > 0
                        ? h('div', {
                            key: '__adv',
                            style: {
                                marginTop: '0.5rem', paddingTop: '0.625rem',
                                borderTop: '1px solid var(--color-border)',
                                fontSize: '0.6875rem', fontWeight: 600,
                                textTransform: 'uppercase' as const, letterSpacing: '0.06em',
                                color: 'var(--color-text-muted)',
                            },
                        }, advancedLabel ?? 'Advanced permissions')
                        : null,
                    ...advancedPerms.map(renderPerm),
                ])
                : null,
        ]);
    };

    const children: (ReturnType<typeof h> | null)[] = [];

    if (onToggleAllGlobal) {
        children.push(h('label', { key: '__all', style: C.pickerGlobalRow }, [
            h('input', {
                key: 'c', type: 'checkbox', checked: !!allSelected,
                ref: (el: HTMLInputElement | null) => { if (el) el.indeterminate = !!someSelected && !allSelected; },
                onChange: onToggleAllGlobal,
                style: C.checkbox,
            }),
            h('span', { key: 'l', style: { flex: 1, fontSize: '0.8125rem', fontWeight: 600, color: 'var(--color-text-primary)' } }, globalLabel ?? 'Select all'),
        ]));
    }

    children.push(h('div', { key: '__scroll', style: { maxHeight: '40vh', overflowY: 'auto' as const } }, groups.map(renderGroup)));

    return h('div', {
        key,
        style: { borderRadius: 'var(--radius)', border: '1px solid var(--color-border)', background: 'var(--color-background)', overflow: 'hidden' as const },
    }, children);
}
