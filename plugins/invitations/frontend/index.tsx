/**
 * Invitations plugin — server Users & Invitations page.
 * Thin orchestrator — presentation lives in ./modals, ./rows, ./permissionPicker, ./serverPicker.
 */
import { api, BASE, C, h, HostServer, Inv, P, PG, S, Sub, svg } from './shared';
import { renderInviteModal, renderEditSubuserModal, renderEditInvitationModal } from './modals';
import { renderServerPicker } from './serverPicker';
import { renderSubuserRow, renderInvitationRow } from './rows';

const { useState, useCallback, useMemo } = S.React;
const { useQuery, useMutation, useQueryClient } = S.ReactQuery;

type EditTarget =
    | { kind: 'subuser'; uuid: string; email: string; permissions: string[] }
    | { kind: 'invitation'; id: number; email: string; permissions: string[] };

/** Owner (permissions === null) or a subuser that holds user.create may invite on a server. */
const canInviteOn = (s: HostServer): boolean =>
    (s.permissions ?? null) === null || (s.permissions ?? []).includes('user.create');

function UsersPage() {
    const { t } = S.useTranslation('invitations');
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

    // Host server list (never hardcoded) — used to offer "invite to other servers".
    const { data: hostRaw } = useQuery({ queryKey: ['inv-host-servers'], queryFn: () => api<{ data: HostServer[] }>(`/api/servers`), staleTime: 60_000 });
    // The current server's egg — copy/multi-invite targets are restricted to it.
    const currentEggId = useMemo<number | null>(() => {
        const all = (hostRaw as { data: HostServer[] } | undefined)?.data ?? [];
        return all.find(s => s.id === serverId)?.egg?.id ?? null;
    }, [hostRaw, serverId]);

    const eligibleServers = useMemo<HostServer[]>(() => {
        const all = (hostRaw as { data: HostServer[] } | undefined)?.data ?? [];
        // Exclude the current server (never shown — implicit target of a fresh
        // invite, irrelevant for copy-access) and restrict to servers running
        // the SAME egg: permissions are egg-specific, so a different egg would
        // receive an invalid permission set.
        return all.filter(s => !!s.identifier && s.id !== serverId && canInviteOn(s)
            && currentEggId !== null && s.egg?.id === currentEggId);
    }, [hostRaw, serverId, currentEggId]);

    const invalidate = (key: string[]) => void qc.invalidateQueries({ queryKey: key });

    const createMut = useMutation({
        mutationFn: (d: { email: string; permissions: string[]; server_ids?: number[] }) => api(`${BASE}/servers/${id}/invitations`, { method: 'POST', body: JSON.stringify(d) }),
        onSuccess: () => invalidate(['invitations', id]),
    });
    const revokeMut = useMutation({
        mutationFn: (invId: number) => api(`${BASE}/invitations/${invId}`, { method: 'DELETE' }),
        onSuccess: () => invalidate(['invitations', id]),
    });
    const resendMut = useMutation({
        mutationFn: (invId: number) => api(`${BASE}/invitations/${invId}/resend`, { method: 'POST' }),
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
    const [copyMode, setCopyMode] = useState(false);
    const [editing, setEditing] = useState<EditTarget | null>(null);
    const [email, setEmail] = useState('');
    const [sel, setSel] = useState<Set<string>>(new Set());
    const [selServers, setSelServers] = useState<Set<number>>(new Set());
    const [exp, setExp] = useState<Set<string>>(new Set());
    const [err, setErr] = useState('');

    const toggle = useCallback((k: string) => setSel(p => { const n = new Set(p); n.has(k) ? n.delete(k) : n.add(k); return n; }), []);
    const toggleAll = useCallback((g: PG) => setSel(p => { const n = new Set(p); const ks = g.permissions.map(x => x.key); ks.every(k => n.has(k)) ? ks.forEach(k => n.delete(k)) : ks.forEach(k => n.add(k)); return n; }), []);
    const toggleExp = useCallback((k: string) => setExp(p => { const n = new Set(p); n.has(k) ? n.delete(k) : n.add(k); return n; }), []);

    // Global "select all permissions" across every group.
    const allKeys = useMemo(() => groups.flatMap(g => g.permissions.map(p => p.key)), [groups]);
    const allPermsSelected = allKeys.length > 0 && allKeys.every(k => sel.has(k));
    const somePermsSelected = allKeys.some(k => sel.has(k));
    const toggleAllGlobal = useCallback(() => setSel(() => (allKeys.every(k => sel.has(k)) && allKeys.length > 0) ? new Set() : new Set(allKeys)), [allKeys, sel]);

    const toggleServer = useCallback((sid: number) => setSelServers(p => { const n = new Set(p); n.has(sid) ? n.delete(sid) : n.add(sid); return n; }), []);
    const toggleAllServers = useCallback(() => setSelServers(p => (eligibleServers.length > 0 && eligibleServers.every(s => p.has(s.id))) ? new Set() : new Set(eligibleServers.map(s => s.id))), [eligibleServers]);

    const closeAll = useCallback(() => {
        setInviteOpen(false); setCopyMode(false); setEditing(null); setEmail(''); setSel(new Set()); setSelServers(new Set()); setErr('');
    }, []);

    const openInvite = useCallback(() => {
        if (inviteOpen) { closeAll(); return; }
        // selServers holds ONLY the extra servers; the current one is implicit.
        setEditing(null); setCopyMode(false); setEmail(''); setSel(new Set()); setSelServers(new Set()); setErr('');
        setInviteOpen(true);
    }, [inviteOpen, closeAll]);

    const openCopy = useCallback((sub: Sub) => {
        setEditing(null); setCopyMode(true); setEmail(sub.email); setSel(new Set(sub.permissions)); setSelServers(new Set()); setErr('');
        setInviteOpen(true);
    }, []);

    const openEditSub = useCallback((sub: Sub) => {
        setInviteOpen(false); setCopyMode(false); setErr('');
        setSel(new Set(sub.permissions));
        setEditing({ kind: 'subuser', uuid: sub.uuid, email: sub.email, permissions: sub.permissions });
    }, []);

    const openEditInv = useCallback((inv: Inv) => {
        setInviteOpen(false); setCopyMode(false); setErr('');
        setSel(new Set(inv.permissions));
        setEditing({ kind: 'invitation', id: inv.id, email: inv.email, permissions: inv.permissions });
    }, []);

    const submitInvite = useCallback(() => {
        setErr('');
        if (!email.trim()) return;
        if (sel.size === 0) { setErr(t('modal.no_permissions')); return; }
        const others = [...selServers];
        // Copy-access → only the chosen servers (current excluded). Fresh invite
        // → the current server is always included, plus any chosen others.
        if (copyMode && others.length === 0) { setErr(t('modal.no_servers', { defaultValue: 'Select at least one server.' })); return; }
        const body = copyMode
            ? { email: email.trim(), permissions: [...sel], server_ids: others }
            : (others.length === 0
                ? { email: email.trim(), permissions: [...sel] }
                : { email: email.trim(), permissions: [...sel], server_ids: [serverId, ...others] });
        createMut.mutate(body, {
            onSuccess: () => closeAll(),
            onError: (e: unknown) => setErr(String((e as Record<string, string>)?.error ?? 'Error')),
        });
    }, [email, sel, selServers, serverId, copyMode, createMut, t, closeAll]);

    const submitEdit = useCallback(() => {
        setErr('');
        if (!editing) return;
        if (sel.size === 0) { setErr(t('modal.no_permissions')); return; }
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
        error: err, cancelLabel: t('cancel', { defaultValue: 'Cancel' }),
        permissionsLabel: t('modal.permissions'),
        emptyLabel: t('modal.loading_permissions', { defaultValue: 'Loading...' }),
        advancedLabel: t('modal.advanced_permissions', { defaultValue: 'Advanced permissions' }),
        allSelected: allPermsSelected, someSelected: somePermsSelected,
        onToggleAllGlobal: toggleAllGlobal, globalLabel: t('modal.select_all', { defaultValue: 'Select all permissions' }),
    };

    const showServerPicker = copyMode || eligibleServers.length > 0;
    const serverPickerNode = showServerPicker
        ? renderServerPicker({
            key: 'srvpicker', servers: eligibleServers, selected: selServers,
            onToggle: toggleServer, onToggleAll: toggleAllServers,
            labels: {
                title: copyMode ? t('modal.servers', { defaultValue: 'Servers' }) : t('modal.servers_more', { defaultValue: 'Also invite to other servers' }),
                hint: copyMode
                    ? t('modal.servers_hint', { defaultValue: 'Pick the servers to invite this user to. Same permissions apply to each.' })
                    : t('modal.servers_hint_invite', { defaultValue: 'This server is always included — pick any additional servers. Same permissions apply to each.' }),
                selectAll: t('modal.select_all_servers', { defaultValue: 'All servers' }),
                empty: t('modal.no_other_servers', { defaultValue: 'No other servers available.' }),
            },
        })
        : null;

    return h('div', { style: C.page }, [
        h('div', { key: 'hdr', style: C.header }, [
            h('div', { key: 'l', style: C.headerLeft }, [
                h('div', { key: 'ic', style: C.iconBox }, svg('M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z')),
                h('div', { key: 'txt' }, [
                    h('h2', { key: 't', style: C.title }, t('page.title')),
                    h('p', { key: 's', style: C.subtitle }, t('page.subtitle')),
                ]),
            ]),
            canInvite ? h('button', { key: 'btn', type: 'button', onClick: openInvite, style: C.btnPrimary }, [
                h('span', { key: 'i' }, svg('M12 6v12m6-6H6', 16, 'currentColor')),
                h('span', { key: 'l' }, t('page.invite_button')),
            ]) : null,
        ]),

        inviteOpen ? renderInviteModal({
            ...pickerCommon,
            key: 'invite-modal',
            title: copyMode ? t('copy.title', { defaultValue: 'Copy access' }) : t('modal.title'),
            subtitle: copyMode ? t('copy.subtitle', { defaultValue: 'Invite this user to other servers with the same permissions.' }) : t('modal.subtitle', { defaultValue: t('page.subtitle') }),
            emailLabel: t('modal.email'),
            email,
            onEmailChange: setEmail,
            emailPlaceholder: t('modal.email_placeholder'),
            serverPicker: serverPickerNode,
            onSubmit: submitInvite, onCancel: closeAll,
            submitLabel: copyMode ? t('copy.send', { defaultValue: 'Send invitations' }) : t('modal.send'),
            isPending: createMut.isPending, disableSubmit: !id,
        }) : null,

        editing && editing.kind === 'subuser' ? renderEditSubuserModal({
            ...pickerCommon,
            key: 'edit-subuser-modal',
            title: t('edit.subuser_title', { defaultValue: 'Edit user permissions' }),
            email: editing.email,
            onSubmit: submitEdit, onCancel: closeAll,
            submitLabel: t('edit.save', { defaultValue: 'Save' }),
            isPending: updateSubMut.isPending,
        }) : null,

        editing && editing.kind === 'invitation' ? renderEditInvitationModal({
            ...pickerCommon,
            key: 'edit-invitation-modal',
            title: t('edit.invitation_title', { defaultValue: 'Edit invitation permissions' }),
            email: editing.email,
            onSubmit: submitEdit, onCancel: closeAll,
            submitLabel: t('edit.save', { defaultValue: 'Save' }),
            isPending: updateInvMut.isPending,
        }) : null,

        subs.length > 0 ? h('div', { key: 'subs', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.625rem' } }, [
            h('p', { key: 'tt', style: C.sectionLabel }, `${t('page.active_users', { defaultValue: 'Active users' })} (${subs.length})`),
            ...subs.map(sub => renderSubuserRow({
                sub, onEdit: openEditSub, onRemove: (uuid) => removeSubMut.mutate(uuid), onCopy: openCopy,
                removeDisabled: removeSubMut.isPending,
                canEdit: canEditUser,
                canRemove: canRemoveUser,
                canCopy: canInvite,
                labels: {
                    active: t('page.status_active', { defaultValue: 'Active' }),
                    remove: t('page.remove', { defaultValue: 'Remove' }),
                    edit: t('edit.button', { defaultValue: 'Edit' }),
                    copy: t('copy.button', { defaultValue: 'Copy access' }),
                    confirmRemove: t('page.confirm_remove', { defaultValue: 'Remove this user?' }),
                    you: t('page.you', { defaultValue: 'You' }),
                },
            })),
        ]) : null,

        invs.length > 0 ? h('div', { key: 'invs', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.625rem' } }, [
            h('p', { key: 'tt', style: C.sectionLabel }, `${t('page.pending_invitations')} (${invs.length})`),
            ...invs.map(inv => renderInvitationRow({
                inv,
                onEdit: openEditInv,
                onRevoke: (iid) => revokeMut.mutate(iid),
                onResend: (iid) => resendMut.mutate(iid),
                revokeDisabled: revokeMut.isPending,
                resendDisabled: resendMut.isPending,
                canEdit: canEditUser,
                canRevoke: canEditUser,
                canResend: canInvite,
                labels: {
                    pending: t('page.status_pending'),
                    expires: t('modal.expires'),
                    revoke: t('modal.revoke'),
                    edit: t('edit.button', { defaultValue: 'Edit' }),
                    resend: t('actions.resend', { defaultValue: 'Resend' }),
                    multiServer: t('page.multi_server', { defaultValue: 'Multi-server' }),
                },
            })),
        ]) : null,

        subs.length === 0 && invs.length === 0 ? h('div', { key: 'empty', style: C.emptyBox }, [
            h('div', { key: 'ic', style: C.emptyIcon },
                svg('M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z', 28, 'var(--color-text-muted)')),
            h('p', { key: 'm', style: { fontSize: '0.875rem', color: 'var(--color-text-muted)', margin: 0 } }, t('page.empty')),
            h('p', { key: 'h', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)', opacity: 0.6, margin: 0 } }, t('page.empty_hint')),
        ]) : null,
    ]);
}

P.registerServerPage('users', UsersPage);
P.register('invitations', () => null);
