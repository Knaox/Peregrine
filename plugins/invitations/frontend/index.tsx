/**
 * Invitations plugin — server Users & Invitations page.
 * Thin orchestrator — presentation lives in ./modals, ./rows, ./permissionPicker.
 */
import { api, BASE, C, h, Inv, P, PG, S, Sub, svg } from './shared';
import { renderInviteModal, renderEditSubuserModal, renderEditInvitationModal } from './modals';
import { renderSubuserRow, renderInvitationRow } from './rows';

const { useState, useCallback } = S.React;
const { useQuery, useMutation, useQueryClient } = S.ReactQuery;

type EditTarget =
    | { kind: 'subuser'; uuid: string; email: string; permissions: string[] }
    | { kind: 'invitation'; id: number; email: string; permissions: string[] };

function UsersPage() {
    const { t } = S.useTranslation();
    const params = S.ReactRouterDom.useParams<{ id: string }>();
    const qc = useQueryClient();
    const serverId = Number(params.id ?? '0');

    const { data: serverData } = useQuery({
        queryKey: ['server-id', serverId],
        queryFn: () => api<{ data: { identifier: string; role?: string | null; permissions?: string[] | null } }>(`/api/servers/${serverId}`).then(r => r.data),
        enabled: serverId > 0,
        staleTime: 5 * 60_000,
    });
    const typed = serverData as { identifier?: string; role?: string | null; permissions?: string[] | null } | undefined;
    const id = typed?.identifier ?? '';
    const isOwner = (typed?.role ?? null) === 'owner' || (typed?.permissions ?? null) === null;
    const myPerms = typed?.permissions ?? [];
    const canInvite = isOwner || myPerms.includes('user.create');
    const canEditUser = isOwner || myPerms.includes('user.update');
    const canRemoveUser = isOwner || myPerms.includes('user.delete');

    const { data: subRaw } = useQuery({ queryKey: ['subusers', id], queryFn: () => api<{ data: Sub[] }>(`${BASE}/servers/${id}/subusers`), enabled: !!id, staleTime: 30_000 });
    const subs: Sub[] = (subRaw as { data: Sub[] })?.data ?? [];

    const { data: invRaw } = useQuery({ queryKey: ['invitations', id], queryFn: () => api<{ data: Inv[] }>(`${BASE}/servers/${id}/invitations`), enabled: !!id, staleTime: 15_000 });
    const invs: Inv[] = (invRaw as { data: Inv[] })?.data ?? [];

    const { data: permRaw } = useQuery({ queryKey: ['inv-perms', id], queryFn: () => api<{ data: PG[] }>(`${BASE}/servers/${id}/permissions`), enabled: !!id, staleTime: 5 * 60_000 });
    const groups: PG[] = (permRaw as { data: PG[] })?.data ?? [];

    const invalidate = (key: string[]) => void qc.invalidateQueries({ queryKey: key });

    const createMut = useMutation({
        mutationFn: (d: { email: string; permissions: string[] }) => api(`${BASE}/servers/${id}/invitations`, { method: 'POST', body: JSON.stringify(d) }),
        onSuccess: () => invalidate(['invitations', id]),
    });
    const revokeMut = useMutation({
        mutationFn: (invId: number) => api(`${BASE}/invitations/${invId}`, { method: 'DELETE' }),
        onSuccess: () => invalidate(['invitations', id]),
    });
    const removeSubMut = useMutation({
        mutationFn: (uuid: string) => api(`${BASE}/servers/${id}/subusers/${uuid}`, { method: 'DELETE' }),
        onSuccess: () => invalidate(['subusers', id]),
    });
    const updateSubMut = useMutation({
        mutationFn: (d: { uuid: string; permissions: string[] }) => api(`${BASE}/servers/${id}/subusers/${d.uuid}`, { method: 'POST', body: JSON.stringify({ permissions: d.permissions }) }),
        onSuccess: () => invalidate(['subusers', id]),
    });
    const updateInvMut = useMutation({
        mutationFn: (d: { id: number; permissions: string[] }) => api(`${BASE}/invitations/${d.id}`, { method: 'PATCH', body: JSON.stringify({ permissions: d.permissions }) }),
        onSuccess: () => invalidate(['invitations', id]),
    });

    const [inviteOpen, setInviteOpen] = useState(false);
    const [editing, setEditing] = useState<EditTarget | null>(null);
    const [email, setEmail] = useState('');
    const [sel, setSel] = useState<Set<string>>(new Set());
    const [exp, setExp] = useState<Set<string>>(new Set());
    const [err, setErr] = useState('');

    const toggle = useCallback((k: string) => setSel(p => { const n = new Set(p); n.has(k) ? n.delete(k) : n.add(k); return n; }), []);
    const toggleAll = useCallback((g: PG) => setSel(p => { const n = new Set(p); const ks = g.permissions.map(x => x.key); ks.every(k => n.has(k)) ? ks.forEach(k => n.delete(k)) : ks.forEach(k => n.add(k)); return n; }), []);
    const toggleExp = useCallback((k: string) => setExp(p => { const n = new Set(p); n.has(k) ? n.delete(k) : n.add(k); return n; }), []);

    const closeAll = useCallback(() => {
        setInviteOpen(false); setEditing(null); setEmail(''); setSel(new Set()); setErr('');
    }, []);

    const openInvite = useCallback(() => {
        setEditing(null); setEmail(''); setSel(new Set()); setErr('');
        setInviteOpen(v => !v);
    }, []);

    const openEditSub = useCallback((sub: Sub) => {
        setInviteOpen(false); setErr('');
        setSel(new Set(sub.permissions));
        setEditing({ kind: 'subuser', uuid: sub.uuid, email: sub.email, permissions: sub.permissions });
    }, []);

    const openEditInv = useCallback((inv: Inv) => {
        setInviteOpen(false); setErr('');
        setSel(new Set(inv.permissions));
        setEditing({ kind: 'invitation', id: inv.id, email: inv.email, permissions: inv.permissions });
    }, []);

    const submitInvite = useCallback(() => {
        setErr('');
        if (!email.trim()) return;
        if (sel.size === 0) { setErr(t('invitations.modal.no_permissions')); return; }
        createMut.mutate({ email: email.trim(), permissions: [...sel] }, {
            onSuccess: () => closeAll(),
            onError: (e: unknown) => setErr(String((e as Record<string, string>)?.error ?? 'Error')),
        });
    }, [email, sel, createMut, t, closeAll]);

    const submitEdit = useCallback(() => {
        setErr('');
        if (!editing) return;
        if (sel.size === 0) { setErr(t('invitations.modal.no_permissions')); return; }
        const onOk = () => closeAll();
        const onKo = (e: unknown) => setErr(String((e as Record<string, string>)?.error ?? 'Error'));
        if (editing.kind === 'subuser') {
            updateSubMut.mutate({ uuid: editing.uuid, permissions: [...sel] }, { onSuccess: onOk, onError: onKo });
        } else {
            updateInvMut.mutate({ id: editing.id, permissions: [...sel] }, { onSuccess: onOk, onError: onKo });
        }
    }, [editing, sel, updateSubMut, updateInvMut, t, closeAll]);

    const pickerCommon = {
        groups, selected: sel, expanded: exp,
        onToggle: toggle, onToggleAll: toggleAll, onToggleExpand: toggleExp,
        error: err, cancelLabel: t('common.cancel', { defaultValue: 'Cancel' }),
        permissionsLabel: t('invitations.modal.permissions'),
        emptyLabel: t('invitations.modal.loading_permissions', { defaultValue: 'Loading...' }),
    };

    return h('div', { style: C.page }, [
        h('div', { key: 'hdr', style: C.header }, [
            h('div', { key: 'l', style: C.headerLeft }, [
                h('div', { key: 'ic', style: C.iconBox }, svg('M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z')),
                h('div', { key: 'txt' }, [
                    h('h2', { key: 't', style: C.title }, t('invitations.page.title')),
                    h('p', { key: 's', style: C.subtitle }, t('invitations.page.subtitle')),
                ]),
            ]),
            canInvite ? h('button', { key: 'btn', type: 'button', onClick: openInvite, style: C.btnPrimary }, [
                h('span', { key: 'i' }, svg('M12 6v12m6-6H6', 16, 'currentColor')),
                h('span', { key: 'l' }, t('invitations.page.invite_button')),
            ]) : null,
        ]),

        inviteOpen ? renderInviteModal({
            ...pickerCommon,
            key: 'invite-modal',
            title: t('invitations.modal.title'),
            email,
            onEmailChange: setEmail,
            emailPlaceholder: t('invitations.modal.email_placeholder'),
            onSubmit: submitInvite, onCancel: closeAll,
            submitLabel: t('invitations.modal.send'),
            isPending: createMut.isPending, disableSubmit: !id,
        }) : null,

        editing && editing.kind === 'subuser' ? renderEditSubuserModal({
            ...pickerCommon,
            key: 'edit-subuser-modal',
            title: t('invitations.edit.subuser_title', { defaultValue: 'Edit user permissions' }),
            email: editing.email,
            onSubmit: submitEdit, onCancel: closeAll,
            submitLabel: t('invitations.edit.save', { defaultValue: 'Save' }),
            isPending: updateSubMut.isPending,
        }) : null,

        editing && editing.kind === 'invitation' ? renderEditInvitationModal({
            ...pickerCommon,
            key: 'edit-invitation-modal',
            title: t('invitations.edit.invitation_title', { defaultValue: 'Edit invitation permissions' }),
            email: editing.email,
            onSubmit: submitEdit, onCancel: closeAll,
            submitLabel: t('invitations.edit.save', { defaultValue: 'Save' }),
            isPending: updateInvMut.isPending,
        }) : null,

        subs.length > 0 ? h('div', { key: 'subs', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.625rem' } }, [
            h('p', { key: 'tt', style: C.sectionLabel }, `${t('invitations.page.active_users', { defaultValue: 'Active users' })} (${subs.length})`),
            ...subs.map(sub => renderSubuserRow({
                sub, onEdit: openEditSub, onRemove: (uuid) => removeSubMut.mutate(uuid),
                removeDisabled: removeSubMut.isPending,
                canEdit: canEditUser,
                canRemove: canRemoveUser,
                labels: {
                    active: t('invitations.page.status_active', { defaultValue: 'Active' }),
                    remove: t('invitations.page.remove', { defaultValue: 'Remove' }),
                    edit: t('invitations.edit.button', { defaultValue: 'Edit' }),
                    confirmRemove: t('invitations.page.confirm_remove', { defaultValue: 'Remove this user?' }),
                    you: t('invitations.page.you', { defaultValue: 'You' }),
                },
            })),
        ]) : null,

        invs.length > 0 ? h('div', { key: 'invs', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.625rem' } }, [
            h('p', { key: 'tt', style: C.sectionLabel }, `${t('invitations.page.pending_invitations')} (${invs.length})`),
            ...invs.map(inv => renderInvitationRow({
                inv, onEdit: openEditInv, onRevoke: (iid) => revokeMut.mutate(iid),
                revokeDisabled: revokeMut.isPending,
                canEdit: canEditUser,
                canRevoke: canEditUser,
                labels: {
                    pending: t('invitations.page.status_pending'),
                    expires: t('invitations.modal.expires'),
                    revoke: t('invitations.modal.revoke'),
                    edit: t('invitations.edit.button', { defaultValue: 'Edit' }),
                },
            })),
        ]) : null,

        subs.length === 0 && invs.length === 0 ? h('div', { key: 'empty', style: C.emptyBox }, [
            h('div', { key: 'ic', style: C.emptyIcon },
                svg('M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z', 28, 'var(--color-text-muted)')),
            h('p', { key: 'm', style: { fontSize: '0.875rem', color: 'var(--color-text-muted)', margin: 0 } }, t('invitations.page.empty')),
            h('p', { key: 'h', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)', opacity: 0.6, margin: 0 } }, t('invitations.page.empty_hint')),
        ]) : null,
    ]);
}

P.registerServerPage('users', UsersPage);
P.register('invitations', () => null);
