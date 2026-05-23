/**
 * Subuser and Invitation row renderers — isolated so they stay small and testable.
 */
import { C, h, Inv, Sub } from './shared';

interface SubuserRowArgs {
    sub: Sub;
    onEdit: (sub: Sub) => void;
    onRemove: (uuid: string) => void;
    onCopy: (sub: Sub) => void;
    removeDisabled: boolean;
    canEdit: boolean;
    canRemove: boolean;
    canCopy: boolean;
    labels: {
        active: string;
        remove: string;
        edit: string;
        copy: string;
        confirmRemove: string;
        you: string;
    };
}

export function renderSubuserRow(args: SubuserRowArgs): ReturnType<typeof h> {
    const { sub, onEdit, onRemove, onCopy, removeDisabled, canEdit, canRemove, canCopy, labels } = args;
    const isSelf = sub.is_current_user === true;

    return h('div', { key: sub.uuid, style: { ...C.card, ...C.userRow } as React.CSSProperties }, [
        h('div', { key: 'info', style: C.userInfo }, [
            sub.image
                ? h('img', {
                    key: 'av', src: sub.image, alt: '',
                    style: { ...C.avatar('', ''), width: 40, height: 40, borderRadius: '50%', objectFit: 'cover' as const },
                })
                : h('div', {
                    key: 'av',
                    style: C.avatar('rgba(var(--color-success-rgb),0.12)', 'var(--color-success)'),
                }, (sub.email || sub.username || '?').charAt(0).toUpperCase()),
            h('div', { key: 'd', style: { minWidth: 0, flex: 1 } }, [
                h('p', { key: 'n', style: C.name }, sub.username || sub.email),
                h('div', { key: 'mt', style: C.meta }, [
                    sub.email !== sub.username
                        ? h('span', { key: 'em', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)' } }, sub.email)
                        : null,
                    h('span', {
                        key: 'st',
                        style: C.badge('rgba(var(--color-success-rgb),0.1)', 'var(--color-success)'),
                    }, labels.active),
                    isSelf
                        ? h('span', { key: 'me', style: C.badge('rgba(var(--color-primary-rgb),0.12)', 'var(--color-primary)') }, labels.you)
                        : null,
                    sub['2fa_enabled']
                        ? h('span', { key: '2f', style: C.badge('rgba(var(--color-info-rgb),0.1)', 'var(--color-info)') }, '2FA')
                        : null,
                ]),
            ]),
        ]),
        h('div', { key: 'ac', style: C.actions }, [
            h('span', { key: 'pc', style: C.permBadge }, `${sub.permissions.length} perms`),
            !isSelf && canCopy
                ? h('button', {
                    key: 'cp', type: 'button', onClick: () => onCopy(sub),
                    style: C.btnSecondary,
                }, labels.copy)
                : null,
            !isSelf && canEdit
                ? h('button', {
                    key: 'ed', type: 'button', onClick: () => onEdit(sub),
                    style: C.btnSecondary,
                }, labels.edit)
                : null,
            !isSelf && canRemove
                ? h('button', {
                    key: 'rm', type: 'button',
                    onClick: () => { if (confirm(labels.confirmRemove)) onRemove(sub.uuid); },
                    disabled: removeDisabled,
                    style: { ...C.btnDanger, opacity: removeDisabled ? 0.5 : 1 },
                }, labels.remove)
                : null,
        ]),
    ]);
}

interface InvitationRowArgs {
    inv: Inv;
    onEdit: (inv: Inv) => void;
    onRevoke: (id: number) => void;
    onResend: (id: number) => void;
    revokeDisabled: boolean;
    resendDisabled: boolean;
    canEdit: boolean;
    canRevoke: boolean;
    canResend: boolean;
    labels: {
        pending: string;
        expires: string;
        revoke: string;
        edit: string;
        resend: string;
        multiServer: string;
    };
}

export function renderInvitationRow(args: InvitationRowArgs): ReturnType<typeof h> {
    const { inv, onEdit, onRevoke, onResend, revokeDisabled, resendDisabled, canEdit, canRevoke, canResend, labels } = args;

    return h('div', { key: inv.id, style: { ...C.card, ...C.userRow } as React.CSSProperties }, [
        h('div', { key: 'info', style: C.userInfo }, [
            h('div', {
                key: 'av',
                style: C.avatar('rgba(var(--color-warning-rgb),0.12)', 'var(--color-warning)'),
            }, inv.email.charAt(0).toUpperCase()),
            h('div', { key: 'd', style: { minWidth: 0, flex: 1 } }, [
                h('p', { key: 'e', style: C.name }, inv.email),
                h('div', { key: 'mt', style: C.meta }, [
                    h('span', {
                        key: 'st',
                        style: C.badge('rgba(var(--color-warning-rgb),0.1)', 'var(--color-warning)'),
                    }, labels.pending),
                    inv.batch_id
                        ? h('span', {
                            key: 'ms',
                            style: C.badge('rgba(var(--color-primary-rgb),0.12)', 'var(--color-primary)'),
                        }, inv.batch_size && inv.batch_size > 1 ? `${labels.multiServer} · ${inv.batch_size}` : labels.multiServer)
                        : null,
                    h('span', {
                        key: 'ex', style: { fontSize: '0.6875rem', color: 'var(--color-text-muted)' },
                    }, `${labels.expires}: ${new Date(inv.expires_at).toLocaleDateString()}`),
                ]),
            ]),
        ]),
        h('div', { key: 'ac', style: C.actions }, [
            h('span', { key: 'pc', style: C.permBadge }, `${inv.permissions.length} perms`),
            canResend
                ? h('button', {
                    key: 'rs', type: 'button', onClick: () => onResend(inv.id),
                    disabled: resendDisabled,
                    style: { ...C.btnSecondary, opacity: resendDisabled ? 0.5 : 1 },
                }, labels.resend)
                : null,
            canEdit
                ? h('button', {
                    key: 'ed', type: 'button', onClick: () => onEdit(inv),
                    style: C.btnSecondary,
                }, labels.edit)
                : null,
            canRevoke
                ? h('button', {
                    key: 'rv', type: 'button', onClick: () => onRevoke(inv.id),
                    disabled: revokeDisabled,
                    style: { ...C.btnDanger, opacity: revokeDisabled ? 0.5 : 1 },
                }, labels.revoke)
                : null,
        ]),
    ]);
}
