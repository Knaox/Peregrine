/**
 * Three modal renderers: InviteModal, EditSubuserModal, EditInvitationModal.
 * Rendered as a centered overlay (scrim + panel); backdrop click cancels.
 * All share the PermissionPicker and the same action/error layout.
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
    advancedLabel: string;
    isPending: boolean;
    disableSubmit?: boolean;
    // Global "select all permissions" affordance (threaded into the picker).
    allSelected?: boolean;
    someSelected?: boolean;
    onToggleAllGlobal?: () => void;
    globalLabel?: string;
    /** Required when placed inside a parent array. */
    key?: string;
}

function renderActions(args: CommonArgs): ReturnType<typeof h> {
    return h('div', { key: 'acts', style: { display: 'flex', gap: '0.5rem', paddingTop: '0.25rem' } }, [
        h('button', {
            key: 's', type: 'button', onClick: args.onSubmit,
            disabled: args.isPending || args.disableSubmit,
            style: { ...C.btnPrimary, opacity: (args.isPending || args.disableSubmit) ? 0.5 : 1 },
        }, args.isPending ? '…' : args.submitLabel),
        h('button', { key: 'c', type: 'button', onClick: args.onCancel, style: C.btnGhost }, args.cancelLabel),
    ]);
}

function renderError(error: string): ReturnType<typeof h> | null {
    return error
        ? h('div', { key: 'err', style: { padding: '0.625rem 0.875rem', fontSize: '0.8125rem', borderRadius: 'var(--radius)', background: 'rgba(var(--color-danger-rgb),0.08)', color: 'var(--color-danger)', border: '1px solid rgba(var(--color-danger-rgb),0.15)' } }, error)
        : null;
}

function renderPicker(args: CommonArgs): ReturnType<typeof h> {
    return renderPermissionPicker({
        key: 'picker', groups: args.groups, selected: args.selected, expanded: args.expanded,
        onToggle: args.onToggle, onToggleAll: args.onToggleAll, onToggleExpand: args.onToggleExpand,
        emptyLabel: args.emptyLabel, advancedLabel: args.advancedLabel,
        allSelected: args.allSelected, someSelected: args.someSelected,
        onToggleAllGlobal: args.onToggleAllGlobal, globalLabel: args.globalLabel,
    });
}

function renderModalChrome(opts: { title: string; subtitle?: string | null; subject?: string | null; body: (ReturnType<typeof h> | null)[]; onCancel: () => void; key?: string }): ReturnType<typeof h> {
    const children: (ReturnType<typeof h> | null)[] = [
        h('div', { key: 'head', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.25rem' } }, [
            h('h3', { key: 't', style: C.modalTitle }, opts.title),
            opts.subtitle ? h('p', { key: 's', style: C.modalSubtitle }, opts.subtitle) : null,
        ]),
    ];

    if (opts.subject) {
        children.push(h('div', {
            key: 'subject',
            style: {
                fontSize: '0.875rem', color: 'var(--color-text-primary)', fontWeight: 600,
                padding: '0.625rem 0.875rem', background: 'var(--color-background)',
                border: '1px solid var(--color-border)', borderRadius: 'var(--radius)',
            },
        }, opts.subject));
    }

    children.push(...opts.body);

    return h('div', { key: opts.key, style: C.overlay, onClick: opts.onCancel }, [
        h('div', { key: 'panel', style: C.modalPanel, onClick: (e: React.MouseEvent) => e.stopPropagation() }, children),
    ]);
}

export function renderInviteModal(args: CommonArgs & {
    email: string;
    onEmailChange: (v: string) => void;
    emailPlaceholder: string;
    emailLabel: string;
    title: string;
    subtitle?: string;
    /** Optional pre-rendered multi-server picker node (inserted after the email). */
    serverPicker?: ReturnType<typeof h> | null;
}): ReturnType<typeof h> {
    return renderModalChrome({
        key: args.key,
        title: args.title,
        subtitle: args.subtitle,
        subject: null,
        onCancel: args.onCancel,
        body: [
            h('div', { key: 'emwrap', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.375rem' } }, [
                h('label', { key: 'eml', style: C.sectionLabel }, args.emailLabel),
                h('input', {
                    key: 'em', type: 'email', value: args.email,
                    onChange: (e: React.ChangeEvent<HTMLInputElement>) => args.onEmailChange(e.target.value),
                    placeholder: args.emailPlaceholder, style: C.input,
                    onFocus: (e: React.FocusEvent<HTMLInputElement>) => { e.currentTarget.style.borderColor = 'var(--color-primary)'; e.currentTarget.style.boxShadow = '0 0 0 3px rgba(var(--color-primary-rgb),0.1)'; },
                    onBlur: (e: React.FocusEvent<HTMLInputElement>) => { e.currentTarget.style.borderColor = 'var(--color-border)'; e.currentTarget.style.boxShadow = 'none'; },
                }),
            ]),
            args.serverPicker ?? null,
            h('div', { key: 'plbl', style: C.sectionLabel }, args.permissionsLabel),
            renderPicker(args),
            renderError(args.error),
            renderActions(args),
        ],
    });
}

function renderEditModal(args: CommonArgs & { title: string; subject: string }): ReturnType<typeof h> {
    return renderModalChrome({
        key: args.key,
        title: args.title,
        subject: args.subject,
        onCancel: args.onCancel,
        body: [
            h('div', { key: 'plbl', style: C.sectionLabel }, args.permissionsLabel),
            renderPicker(args),
            renderError(args.error),
            renderActions(args),
        ],
    });
}

export function renderEditSubuserModal(args: CommonArgs & { title: string; email: string }): ReturnType<typeof h> {
    return renderEditModal({ ...args, subject: args.email });
}

export function renderEditInvitationModal(args: CommonArgs & { title: string; email: string }): ReturnType<typeof h> {
    return renderEditModal({ ...args, subject: args.email });
}
