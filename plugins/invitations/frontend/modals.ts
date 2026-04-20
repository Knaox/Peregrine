/**
 * Three modal renderers: InviteModal, EditSubuserModal, EditInvitationModal.
 * All share the PermissionPicker and the same form layout — only header, submit label, and target subject differ.
 */
import { C, h, PG } from './shared';
import { renderPermissionPicker } from './permissionPicker';

interface CommonArgs {
    groups: PG[];
    selected: Set<string>;
    expanded: Set<string>;
    onToggle: (key: string) => void;
    onToggleAll: (group: PG) => void;
    onToggleExpand: (key: string) => void;
    onSubmit: () => void;
    onCancel: () => void;
    error: string;
    submitLabel: string;
    cancelLabel: string;
    permissionsLabel: string;
    emptyLabel: string;
    isPending: boolean;
    disableSubmit?: boolean;
    /** Required when placed inside a parent array. */
    key?: string;
}

function renderModalChrome(opts: { title: string; subject: string | null; body: ReturnType<typeof h>[]; key?: string }): ReturnType<typeof h> {
    const children: ReturnType<typeof h>[] = [
        h('h3', { key: 'ft', style: { ...C.sectionLabel, marginBottom: '-0.25rem' } }, opts.title),
    ];

    if (opts.subject !== null) {
        children.push(
            h('div', {
                key: 'subject',
                style: {
                    fontSize: '0.875rem', color: 'var(--color-text-primary)', fontWeight: 600,
                    padding: '0.625rem 0.875rem', background: 'var(--color-background)',
                    border: '1px solid var(--color-border)', borderRadius: 'var(--radius)',
                },
            }, opts.subject),
        );
    }

    children.push(...opts.body);

    return h('div', {
        key: opts.key,
        style: { ...C.card, display: 'flex', flexDirection: 'column' as const, gap: '1rem' },
    }, children);
}

export function renderInviteModal(args: CommonArgs & { email: string; onEmailChange: (v: string) => void; emailPlaceholder: string; title: string }): ReturnType<typeof h> {
    return renderModalChrome({
        key: args.key,
        title: args.title,
        subject: null,
        body: [
            h('input', {
                key: 'em', type: 'email', value: args.email,
                onChange: (e: React.ChangeEvent<HTMLInputElement>) => args.onEmailChange(e.target.value),
                placeholder: args.emailPlaceholder, style: C.input,
                onFocus: (e: React.FocusEvent<HTMLInputElement>) => { e.currentTarget.style.borderColor = 'var(--color-primary)'; e.currentTarget.style.boxShadow = '0 0 0 3px rgba(var(--color-primary-rgb),0.1)'; },
                onBlur: (e: React.FocusEvent<HTMLInputElement>) => { e.currentTarget.style.borderColor = 'var(--color-border)'; e.currentTarget.style.boxShadow = 'none'; },
            }),
            h('div', { key: 'plbl', style: C.sectionLabel }, args.permissionsLabel),
            renderPermissionPicker({ key: 'picker', groups: args.groups, selected: args.selected, expanded: args.expanded, onToggle: args.onToggle, onToggleAll: args.onToggleAll, onToggleExpand: args.onToggleExpand, emptyLabel: args.emptyLabel }),
            args.error
                ? h('div', { key: 'err', style: { padding: '0.625rem 0.875rem', fontSize: '0.8125rem', borderRadius: 'var(--radius)', background: 'rgba(var(--color-danger-rgb),0.08)', color: 'var(--color-danger)', border: '1px solid rgba(var(--color-danger-rgb),0.15)' } }, args.error)
                : null,
            h('div', { key: 'acts', style: { display: 'flex', gap: '0.5rem', paddingTop: '0.25rem' } }, [
                h('button', {
                    key: 's', type: 'button', onClick: args.onSubmit,
                    disabled: args.isPending || args.disableSubmit,
                    style: { ...C.btnPrimary, opacity: (args.isPending || args.disableSubmit) ? 0.5 : 1 },
                }, args.isPending ? '...' : args.submitLabel),
                h('button', { key: 'c', type: 'button', onClick: args.onCancel, style: C.btnGhost }, args.cancelLabel),
            ]),
        ],
    });
}

function renderEditModal(args: CommonArgs & { title: string; subject: string }): ReturnType<typeof h> {
    return renderModalChrome({
        key: args.key,
        title: args.title,
        subject: args.subject,
        body: [
            h('div', { key: 'plbl', style: C.sectionLabel }, args.permissionsLabel),
            renderPermissionPicker({ key: 'picker', groups: args.groups, selected: args.selected, expanded: args.expanded, onToggle: args.onToggle, onToggleAll: args.onToggleAll, onToggleExpand: args.onToggleExpand, emptyLabel: args.emptyLabel }),
            args.error
                ? h('div', { key: 'err', style: { padding: '0.625rem 0.875rem', fontSize: '0.8125rem', borderRadius: 'var(--radius)', background: 'rgba(var(--color-danger-rgb),0.08)', color: 'var(--color-danger)', border: '1px solid rgba(var(--color-danger-rgb),0.15)' } }, args.error)
                : null,
            h('div', { key: 'acts', style: { display: 'flex', gap: '0.5rem', paddingTop: '0.25rem' } }, [
                h('button', {
                    key: 's', type: 'button', onClick: args.onSubmit,
                    disabled: args.isPending || args.disableSubmit,
                    style: { ...C.btnPrimary, opacity: (args.isPending || args.disableSubmit) ? 0.5 : 1 },
                }, args.isPending ? '...' : args.submitLabel),
                h('button', { key: 'c', type: 'button', onClick: args.onCancel, style: C.btnGhost }, args.cancelLabel),
            ]),
        ],
    });
}

export function renderEditSubuserModal(args: CommonArgs & { title: string; email: string }): ReturnType<typeof h> {
    return renderEditModal({ ...args, subject: args.email });
}

export function renderEditInvitationModal(args: CommonArgs & { title: string; email: string }): ReturnType<typeof h> {
    return renderEditModal({ ...args, subject: args.email });
}
