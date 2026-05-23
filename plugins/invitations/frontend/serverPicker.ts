/**
 * Multi-server selector for the invite / copy-access flow. Lets the inviter
 * pick every server the same user should be invited to — a single email then
 * covers them all. The server list is supplied by the caller (sourced from the
 * host `GET /api/servers`, never hardcoded) already filtered to servers the
 * user may invite on.
 */
import { C, h, HostServer } from './shared';

interface Args {
    servers: HostServer[];
    selected: Set<number>;
    onToggle: (id: number) => void;
    onToggleAll: () => void;
    labels: {
        title: string;
        hint: string;
        selectAll: string;
        empty: string;
    };
    key?: string;
}

export function renderServerPicker(args: Args): ReturnType<typeof h> {
    const { servers, selected, onToggle, onToggleAll, labels, key } = args;

    const header = h('div', { key: 'lbl', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.25rem' } }, [
        h('p', { key: 't', style: C.sectionLabel }, labels.title),
        h('p', { key: 'h', style: C.hint }, labels.hint),
    ]);

    if (servers.length === 0) {
        return h('div', { key, style: { display: 'flex', flexDirection: 'column' as const, gap: '0.5rem' } }, [
            header,
            h('p', { key: 'e', style: { ...C.hint, padding: '0.75rem', textAlign: 'center' as const, border: '1px dashed var(--color-border)', borderRadius: 'var(--radius)' } }, labels.empty),
        ]);
    }

    const allSelected = servers.every(s => selected.has(s.id));
    const someSelected = servers.some(s => selected.has(s.id));
    const selectedCount = servers.filter(s => selected.has(s.id)).length;

    return h('div', { key, style: { display: 'flex', flexDirection: 'column' as const, gap: '0.5rem' } }, [
        header,
        h('div', { key: 'box', style: { borderRadius: 'var(--radius)', border: '1px solid var(--color-border)', background: 'var(--color-background)', overflow: 'hidden' } }, [
            h('label', { key: 'all', style: C.pickerGlobalRow }, [
                h('input', {
                    key: 'c', type: 'checkbox', checked: allSelected,
                    ref: (el: HTMLInputElement | null) => { if (el) el.indeterminate = someSelected && !allSelected; },
                    onChange: onToggleAll, style: C.checkbox,
                }),
                h('span', { key: 'l', style: { flex: 1, fontSize: '0.8125rem', fontWeight: 600, color: 'var(--color-text-primary)' } }, labels.selectAll),
                h('span', { key: 'n', style: C.permBadge }, `${selectedCount}/${servers.length}`),
            ]),
            h('div', { key: 'list', style: { ...C.serverList, padding: '0.5rem' } },
                servers.map(s => h('label', {
                    key: s.id,
                    style: { ...C.serverOpt, background: selected.has(s.id) ? 'var(--color-surface-hover)' : 'transparent', borderColor: selected.has(s.id) ? 'rgba(var(--color-primary-rgb),0.4)' : 'var(--color-border)' },
                }, [
                    h('input', { key: 'c', type: 'checkbox', checked: selected.has(s.id), onChange: () => onToggle(s.id), style: C.checkbox }),
                    h('span', { key: 'd', style: { minWidth: 0, flex: 1 } }, [
                        h('span', { key: 'n', style: { display: 'block', fontSize: '0.8125rem', fontWeight: 500, color: 'var(--color-text-primary)', overflow: 'hidden' as const, textOverflow: 'ellipsis' as const, whiteSpace: 'nowrap' as const } }, s.name),
                        s.egg?.name ? h('span', { key: 'e', style: { display: 'block', fontSize: '0.6875rem', color: 'var(--color-text-muted)' } }, s.egg.name) : null,
                    ]),
                ])),
            ),
        ]),
    ]);
}
