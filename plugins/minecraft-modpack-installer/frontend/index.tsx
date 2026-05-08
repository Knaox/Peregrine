/**
 * Minecraft: Modpack — Installer plugin. Server-scoped Modpacks page.
 *
 * Layout (top to bottom) :
 *   - Currently installed card (conditional)
 *   - Filters bar (provider / mc / loader / search / size)
 *   - Missing API key notice (conditional, when CurseForge is selected and unconfigured)
 *   - Modpack grid + pagination
 *   - Install / uninstall modals
 *
 * The page polls the installation endpoint while a modpack operation is in
 * progress (every 4s) so the UI flips to "completed" within one round-trip
 * once the poll job finalises the install.
 */
import {
    api, BASE, C, h, P, PROVIDER_LABEL_KEY, S, svg,
    type Capabilities, type Category, type InstallationState, type ModpackHit, type ModpackVersion,
    type Provider, type SearchMeta,
} from './shared';
import { renderInstallModal, renderUninstallModal } from './modals';

const { useState, useMemo, useEffect, useRef } = S.React;
const { useQuery, useMutation, useQueryClient } = S.ReactQuery;

const PAGE_SIZES = [10, 25, 50] as const;
const DEFAULT_PAGE_SIZE = 25;

interface SearchResponse {
    data: ModpackHit[];
    meta: SearchMeta;
}

function ModpacksPage() {
    const { t } = S.useTranslation('minecraft-modpack-installer');
    const params = S.ReactRouterDom.useParams<{ id: string }>();
    const qc = useQueryClient();
    const serverId = Number(params.id ?? '0');

    // Resolve the server's identifier (the API uses identifier, not numeric id).
    const { data: serverData } = useQuery({
        queryKey: ['server-id', serverId],
        queryFn: () => api<{ data: { identifier: string; role?: string | null; permissions?: string[] | null } }>(`/api/servers/${serverId}`).then(r => r.data),
        enabled: serverId > 0,
        staleTime: 5 * 60_000,
    });
    const typed = serverData as { identifier?: string; role?: string | null; permissions?: string[] | null } | undefined;
    const identifier = typed?.identifier ?? '';
    const isOwner = (typed?.role ?? null) === 'owner' || (typed?.permissions ?? null) === null;
    const myPerms = typed?.permissions ?? [];
    const can = (perm: string) => isOwner || (Array.isArray(myPerms) && myPerms.includes(perm));

    // ---------------------------------------------------------------------
    // Eligibility
    // ---------------------------------------------------------------------
    const eligibilityQ = useQuery({
        queryKey: ['mp', identifier, 'eligibility'],
        queryFn: () => api<{ data: { eligible: boolean; reason: string | null } }>(`${BASE}/servers/${identifier}/modpacks/eligibility`),
        enabled: !!identifier,
        staleTime: 60_000,
    });
    const eligible = (eligibilityQ.data as { data?: { eligible?: boolean } } | undefined)?.data?.eligible ?? false;

    // ---------------------------------------------------------------------
    // Providers
    // ---------------------------------------------------------------------
    interface ProvidersMeta {
        default_provider: string | null;
        default_sort: string;
        default_page_size: number;
    }
    const providersQ = useQuery({
        queryKey: ['mp', identifier, 'providers'],
        queryFn: () => api<{ data: Provider[]; meta?: ProvidersMeta }>(`${BASE}/servers/${identifier}/modpacks/providers`),
        enabled: !!identifier && eligible,
        staleTime: 5 * 60_000,
    });
    const providersResp = providersQ.data as { data?: Provider[]; meta?: ProvidersMeta } | undefined;
    const providers: Provider[] = providersResp?.data ?? [];
    const providersMeta = providersResp?.meta;

    // ---------------------------------------------------------------------
    // Filters state
    // ---------------------------------------------------------------------
    const [providerId, setProviderId] = useState<string>('');
    const [mcVersion, setMcVersion] = useState<string>('');
    const [loader, setLoader] = useState<string>('');
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [committedSearch, setCommittedSearch] = useState<string>('');
    const [pageSize, setPageSize] = useState<number>(DEFAULT_PAGE_SIZE);
    const [page, setPage] = useState<number>(1);
    const [sort, setSort] = useState<string>('');     // '' = provider default
    const [category, setCategory] = useState<string>('');

    // Initialise providerId from the providers list once it lands. Order
    // of preference :
    //   1. Admin-configured `default_provider` if it's actually configured
    //      (e.g. CurseForge selected as default but no API key → skip).
    //      The API only returns it in meta when both conditions hold.
    //   2. First configured provider on the list.
    //   3. First provider on the list (always populated client-side fallback).
    //
    // Without (1), the admin's `default_provider` setting in
    // /admin/modpack-configs was completely ignored — the page always
    // landed on Modrinth (alphabetically first configured) regardless of
    // what the operator picked.
    if (providerId === '' && providers.length > 0) {
        const adminDefaultId = providersMeta?.default_provider ?? null;
        const adminDefault = adminDefaultId !== null
            ? providers.find(p => p.id === adminDefaultId && p.configured)
            : null;
        const firstConfigured = providers.find(p => p.configured) ?? providers[0];
        const chosen = adminDefault ?? firstConfigured;
        if (chosen) {
            // setState in render is allowed for one-shot init when using current values.
            queueMicrotask(() => setProviderId(chosen.id));
        }
    }

    // Honor the admin-configured page size on first render. Without this,
    // the SPA always opens with the hardcoded DEFAULT_PAGE_SIZE constant,
    // ignoring whatever the operator picked in /admin/modpack-configs.
    const initialPageSizeRef = useRef<boolean>(false);
    if (!initialPageSizeRef.current && providersMeta && providersMeta.default_page_size > 0 && providersMeta.default_page_size !== pageSize) {
        initialPageSizeRef.current = true;
        const adminPageSize = providersMeta.default_page_size;
        if (PAGE_SIZES.includes(adminPageSize as typeof PAGE_SIZES[number])) {
            queueMicrotask(() => setPageSize(adminPageSize));
        }
    }

    const currentProvider = useMemo(() => providers.find(p => p.id === providerId) ?? null, [providers, providerId]);
    const caps: Capabilities | null = currentProvider?.capabilities ?? null;

    const resetForProvider = (newId: string) => {
        setProviderId(newId);
        setMcVersion('');
        setLoader('');
        setSort('');
        setCategory('');
        setPage(1);
    };

    // ---------------------------------------------------------------------
    // Minecraft versions (provider-conditional)
    // ---------------------------------------------------------------------
    const mcVersionsQ = useQuery({
        queryKey: ['mp', identifier, 'mc-versions', providerId],
        queryFn: () => api<{ data: string[] }>(`${BASE}/servers/${identifier}/modpacks/providers/${providerId}/minecraft-versions`),
        enabled: !!identifier && !!providerId && (caps?.minecraft_version_filter ?? false),
        staleTime: 6 * 60 * 60_000,
    });
    const mcVersions: string[] = (mcVersionsQ.data as { data?: string[] } | undefined)?.data ?? [];

    // ---------------------------------------------------------------------
    // Categories / tags (provider-conditional)
    // ---------------------------------------------------------------------
    const categoriesQ = useQuery({
        queryKey: ['mp', identifier, 'categories', providerId],
        queryFn: () => api<{ data: Category[] }>(`${BASE}/servers/${identifier}/modpacks/providers/${providerId}/categories`),
        enabled: !!identifier && !!providerId && (caps?.category_filter ?? false),
        staleTime: 12 * 60 * 60_000,
    });
    const categories: Category[] = (categoriesQ.data as { data?: Category[] } | undefined)?.data ?? [];

    // ---------------------------------------------------------------------
    // Search
    // ---------------------------------------------------------------------
    const searchEnabled = !!identifier && !!providerId && (currentProvider?.configured ?? false);
    const searchQ = useQuery({
        queryKey: ['mp', identifier, 'search', providerId, committedSearch, mcVersion, loader, sort, category, page, pageSize],
        queryFn: () => {
            const url = new URL(`${BASE}/servers/${identifier}/modpacks/search`, window.location.origin);
            url.searchParams.set('provider', providerId);
            if (committedSearch) url.searchParams.set('q', committedSearch);
            if (mcVersion) url.searchParams.set('mc', mcVersion);
            if (loader) url.searchParams.set('loader', loader);
            if (sort) url.searchParams.set('sort', sort);
            if (category) url.searchParams.set('category', category);
            url.searchParams.set('page', String(page));
            url.searchParams.set('size', String(pageSize));
            return api<SearchResponse>(url.pathname + url.search);
        },
        enabled: searchEnabled,
        staleTime: 30_000,
        placeholderData: (prev) => prev,
    });
    const searchResp = searchQ.data as SearchResponse | undefined;
    const hits: ModpackHit[] = searchResp?.data ?? [];
    const meta: SearchMeta | null = searchResp?.meta ?? null;

    // ---------------------------------------------------------------------
    // Installation state (with active-polling)
    // ---------------------------------------------------------------------
    const installationQ = useQuery({
        queryKey: ['mp', identifier, 'installation'],
        queryFn: () => api<{ data: InstallationState | null }>(`${BASE}/servers/${identifier}/modpacks/installation`),
        enabled: !!identifier,
        staleTime: 5_000,
        refetchInterval: (query) => {
            const data = (query.state.data as { data?: InstallationState | null } | undefined)?.data;
            return data?.is_active ? 4000 : false;
        },
    });
    const installation: InstallationState | null = (installationQ.data as { data?: InstallationState | null } | undefined)?.data ?? null;

    // ---------------------------------------------------------------------
    // Install / uninstall mutations
    // ---------------------------------------------------------------------
    const [installTarget, setInstallTarget] = useState<ModpackHit | null>(null);
    const [installError, setInstallError] = useState<string | null>(null);
    const [uninstallOpen, setUninstallOpen] = useState<boolean>(false);
    const [uninstallError, setUninstallError] = useState<string | null>(null);

    // Intentionally fetched WITHOUT the marketplace's MC filter so the modal
    // can show every version that exists for the modpack — and let the user
    // narrow on the spot. Filtering server-side here would amputate the list
    // without any way to recover it without closing the modal.
    const installVersionsQ = useQuery({
        queryKey: ['mp', identifier, 'versions', installTarget?.provider, installTarget?.modpack_id],
        queryFn: () => {
            if (!installTarget) return Promise.resolve({ data: [] });
            const u = `${BASE}/servers/${identifier}/modpacks/${installTarget.provider}/${encodeURIComponent(installTarget.modpack_id)}/versions`;
            return api<{ data: ModpackVersion[] }>(u);
        },
        enabled: !!installTarget,
        staleTime: 30 * 60_000,
    });
    const versionList: ModpackVersion[] = (installVersionsQ.data as { data?: ModpackVersion[] } | undefined)?.data ?? [];

    const navigate = S.ReactRouterDom.useNavigate();

    const installMut = useMutation({
        mutationFn: (d: { provider: string; modpack_id: string; version_id: string; purge_files: boolean }) =>
            api<{ data: InstallationState }>(`${BASE}/servers/${identifier}/modpacks/installation`, {
                method: 'POST', body: JSON.stringify(d),
            }),
        onSuccess: (resp) => {
            const data = (resp as { data?: InstallationState } | undefined)?.data ?? null;
            const modpackName = data?.modpack_name ?? installTarget?.name ?? '';
            setInstallTarget(null);
            setInstallError(null);
            void qc.invalidateQueries({ queryKey: ['mp', identifier, 'installation'] });
            // Tell the shell a long-running plugin operation just started so
            // it suppresses its own server-status redirects until we notify
            // completion (or the cooldown clears).
            P.notifyOperationStart('modpack', { serverId, name: modpackName });
            // Redirect to the live console with state for the install banner.
            navigate(`/servers/${serverId}/console`, {
                state: { modpackInstallingBanner: true, modpackName },
            });
        },
        onError: (e: unknown) => {
            const errKey = String((e as Record<string, string>)?.error ?? '');
            setInstallError(errKey ? t(errKey) : t('modpacks.errors.unknown'));
        },
    });

    const uninstallMut = useMutation({
        mutationFn: () => api<{ data: InstallationState }>(`${BASE}/servers/${identifier}/modpacks/installation`, { method: 'DELETE' }),
        onSuccess: () => {
            setUninstallOpen(false);
            setUninstallError(null);
            void qc.invalidateQueries({ queryKey: ['mp', identifier, 'installation'] });
            const modpackName = installation?.modpack_name ?? '';
            P.notifyOperationStart('modpack_uninstall', { serverId, name: modpackName });
            navigate(`/servers/${serverId}/console`, {
                state: { modpackInstallingBanner: true, modpackName },
            });
        },
        onError: (e: unknown) => {
            const errKey = String((e as Record<string, string>)?.error ?? '');
            setUninstallError(errKey ? t(errKey) : t('modpacks.errors.unknown'));
        },
    });

    // ---------------------------------------------------------------------
    // Live updates via Reverb
    // ---------------------------------------------------------------------
    // Subscribe to the server's mirror channel so install / uninstall
    // completion (PollInstallStatusJob → setLocalServerStatus → broadcasts
    // ServerMirrorChanged) lands in <100 ms instead of waiting on the 4 s
    // polling cycle. Without this hook the page only catches the change
    // through the polling tick — which itself stops as soon as
    // `installation.is_active` flips, so a borderline race could leave the
    // UI stuck on the "in progress" card until the user clicks elsewhere.
    //
    // Echo is OPTIONAL : `S.getEcho` is undefined on host shells that
    // pre-date the bridge export, and it can return null when the admin
    // never set up Reverb. In both cases we silently fall back to the
    // existing polling (no crash, no warning spam).
    useEffect(() => {
        if (serverId <= 0 || identifier === '') return;
        const echo = S.getEcho?.();
        if (!echo) return;

        const channel = echo.private(`server.${serverId}`);
        const handler = () => {
            // Server-level mirror changes (status / egg flips) coincide
            // with every modpack op transition point — invalidate the
            // plugin's queries so the UI repaints with fresh data.
            // Eligibility is invalidated too because changing the server
            // egg can flip eligibility on/off.
            void qc.invalidateQueries({ queryKey: ['mp', identifier, 'installation'] });
            void qc.invalidateQueries({ queryKey: ['mp', identifier, 'eligibility'] });
        };
        channel.listen('.mirror.changed', handler);

        return () => {
            try {
                channel.stopListening('.mirror.changed');
                echo.leave(`server.${serverId}`);
            } catch {
                // Tearing down a half-broken channel can throw — safe
                // to ignore, the next subscriber will rebuild from
                // scratch via the lazy `getEcho()` cache.
            }
        };
    }, [serverId, identifier, qc]);

    // Watch the installation state and notify the shell when a plugin-managed
    // operation transitions from active → done. Also force-invalidate the
    // global server query so the rest of the SPA (overview, console gates)
    // refreshes even on installs without Reverb.
    const prevActiveRef = useRef<{ active: boolean; type: string | null; name: string | null }>({
        active: false, type: null, name: null,
    });
    useEffect(() => {
        const prev = prevActiveRef.current;
        const nextActive = installation?.is_active ?? false;
        const nextType = installation?.status === 'uninstalling' ? 'modpack_uninstall' : 'modpack';
        const nextName = installation?.modpack_name ?? null;

        // Track the last seen ACTIVE op so the completion notification can
        // reuse the right name even if the row got deleted (uninstall path).
        if (nextActive) {
            prevActiveRef.current = { active: true, type: nextType, name: nextName };
            return;
        }

        // Transition active → not-active : completed (or failed).
        if (prev.active && !nextActive) {
            prevActiveRef.current = { active: false, type: null, name: null };
            void qc.invalidateQueries({ queryKey: ['servers', serverId] });
            // Only notify completion on success — for `failed` we leave the
            // user on the modpack page where the error is rendered inline.
            const lastStatus = installation?.status ?? null;
            const isCompletion = lastStatus === 'completed'
                || (lastStatus === null && prev.type === 'modpack_uninstall'); // row deleted = uninstall succeeded
            if (isCompletion) {
                P.notifyOperationComplete(prev.type ?? 'modpack', { serverId, name: prev.name });
            }
        }
    }, [installation?.is_active, installation?.status, installation?.modpack_name, serverId, qc]);

    // ---------------------------------------------------------------------
    // Render
    // ---------------------------------------------------------------------

    if (!eligibilityQ.isLoading && !eligible) {
        return h('div', { style: C.page }, [
            renderHeader(t),
            h('div', { key: 'na', style: { ...C.glassCard, textAlign: 'center' as const, padding: '4rem 1rem' } }, [
                h('div', { key: 'i', style: { ...C.iconBox, margin: '0 auto 1rem', width: 56, height: 56 } },
                    svg('M16.5 9.4 7.55 4.24 M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z', 28)),
                h('p', { key: 'l', style: { fontSize: '0.875rem', color: 'var(--color-text-secondary)', margin: 0 } },
                    t('modpacks.errors.server_not_eligible')),
                h('p', { key: 'h', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)', marginTop: '0.5rem' } },
                    t('modpacks.eligibility.help')),
            ]),
        ]);
    }

    return h('div', { style: C.page }, [
        renderHeader(t),

        installation
            ? renderCurrent(installation, can('modpack.uninstall'), () => setUninstallOpen(true), t)
            : null,

        renderFilters({
            providers, providerId, setProviderId: resetForProvider,
            mcVersions, mcVersion, setMcVersion: (v) => { setMcVersion(v); setPage(1); },
            loader, setLoader: (v) => { setLoader(v); setPage(1); },
            sort, setSort: (v) => { setSort(v); setPage(1); },
            categories, category, setCategory: (v) => { setCategory(v); setPage(1); },
            searchTerm, setSearchTerm,
            commitSearch: () => { setCommittedSearch(searchTerm); setPage(1); },
            pageSize, setPageSize: (v) => { setPageSize(v); setPage(1); },
            caps,
            t,
        }),

        currentProvider && !currentProvider.configured
            ? renderMissingApiKey(currentProvider, t)
            : null,

        searchEnabled
            ? renderResults({
                hits, meta, isLoading: searchQ.isLoading || searchQ.isFetching,
                isError: searchQ.isError,
                page, pageSize, setPage,
                canInstall: can('modpack.install'),
                hasInstallActive: installation?.is_active ?? false,
                onInstall: (hit) => { setInstallTarget(hit); setInstallError(null); },
                serverMarkerSupported: caps?.server_marker ?? false,
                t,
            })
            : null,

        installTarget ? renderInstallModal({
            open: true,
            t,
            modpackName: installTarget.name,
            versions: versionList,
            isLoadingVersions: installVersionsQ.isLoading || installVersionsQ.isFetching,
            isSubmitting: installMut.isPending,
            error: installError,
            minecraftVersionFilter: mcVersion || null,
            onCancel: () => { setInstallTarget(null); setInstallError(null); },
            onConfirm: (versionId, purge) => {
                if (!installTarget) return;
                installMut.mutate({
                    provider: installTarget.provider,
                    modpack_id: installTarget.modpack_id,
                    version_id: versionId,
                    purge_files: purge,
                });
            },
        }) : null,

        renderUninstallModal({
            open: uninstallOpen,
            t,
            modpackName: installation?.modpack_name ?? '',
            isSubmitting: uninstallMut.isPending,
            error: uninstallError,
            onCancel: () => { setUninstallOpen(false); setUninstallError(null); },
            onConfirm: () => uninstallMut.mutate(),
        }),
    ]);
}

// ---------------------------------------------------------------------------
// Sub-renderers
// ---------------------------------------------------------------------------

function renderHeader(t: (k: string, o?: Record<string, unknown>) => string): ReturnType<typeof h> {
    return h('div', { key: 'hdr', style: C.header }, [
        h('div', { key: 'l', style: C.headerLeft }, [
            h('div', { key: 'ic', style: C.iconBox },
                svg('M16.5 9.4 7.55 4.24 M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z')),
            h('div', { key: 't' }, [
                h('h2', { key: 't', style: C.title }, t('modpacks.tab.label')),
                h('p', { key: 's', style: C.subtitle }, t('modpacks.subtitle')),
            ]),
        ]),
    ]);
}

function renderCurrent(
    inst: InstallationState,
    canUninstall: boolean,
    openUninstall: () => void,
    t: (k: string, o?: Record<string, unknown>) => string,
): ReturnType<typeof h> {
    const providerName = t(PROVIDER_LABEL_KEY(inst.provider));

    return h('div', { key: 'cur', style: { ...C.card, display: 'flex', gap: '1rem', alignItems: 'center', flexWrap: 'wrap' as const } }, [
        h('div', { key: 'thumb', style: { width: 96, height: 96, borderRadius: 'var(--radius)', background: 'var(--color-surface-elevated, var(--color-surface))', overflow: 'hidden' as const, flexShrink: 0 } },
            inst.icon_url
                ? h('img', { src: inst.icon_url, alt: '', style: { width: '100%', height: '100%', objectFit: 'cover' as const } })
                : h('div', { style: { width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--color-text-muted)' } },
                    svg('M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z', 32))
        ),
        h('div', { key: 'meta', style: { flex: 1, minWidth: 200, display: 'flex', flexDirection: 'column' as const, gap: '0.25rem' } }, [
            h('div', { key: 'top', style: { display: 'flex', alignItems: 'center', gap: '0.375rem', flexWrap: 'wrap' as const } }, [
                h('p', { key: 'name', style: { ...C.cardName, margin: 0 } }, inst.modpack_name),
                inst.is_active
                    ? h('span', { key: 'ip', style: C.badge('rgba(var(--color-info-rgb,59 130 246),0.12)', 'var(--color-info, #3b82f6)') }, t('modpacks.current.installation_in_progress_badge'))
                    : null,
            ]),
            h('p', { key: 'p', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)', margin: 0 } }, providerName + (inst.version_label ? ` — ${inst.version_label}` : '')),
        ]),
        h('div', { key: 'actions', style: { display: 'flex', gap: '0.5rem', flexShrink: 0 } }, [
            inst.external_url
                ? h('a', { key: 'view', href: inst.external_url, target: '_blank', rel: 'noopener noreferrer', style: { ...C.btnGhost, textDecoration: 'none' } },
                    t('modpacks.current.cta_view_external', { provider: providerName }))
                : null,
            canUninstall && !inst.is_active
                ? h('button', { key: 'rm', type: 'button', onClick: openUninstall, style: C.btnDanger }, t('modpacks.current.cta_uninstall'))
                : null,
        ]),
    ]);
}

interface FiltersProps {
    providers: Provider[];
    providerId: string;
    setProviderId: (id: string) => void;
    mcVersions: string[];
    mcVersion: string;
    setMcVersion: (v: string) => void;
    loader: string;
    setLoader: (v: string) => void;
    sort: string;
    setSort: (v: string) => void;
    categories: Category[];
    category: string;
    setCategory: (v: string) => void;
    searchTerm: string;
    setSearchTerm: (v: string) => void;
    commitSearch: () => void;
    pageSize: number;
    setPageSize: (v: number) => void;
    caps: Capabilities | null;
    t: (k: string, o?: Record<string, unknown>) => string;
}

function renderFilters(p: FiltersProps): ReturnType<typeof h> {
    const t = p.t;
    return h('div', { key: 'filters', style: { display: 'flex', flexWrap: 'wrap' as const, gap: '0.5rem', alignItems: 'center' } }, [
        h('select', {
            key: 'provider',
            value: p.providerId,
            onChange: (e: { target: { value: string } }) => p.setProviderId(e.target.value),
            style: C.select,
            'aria-label': t('modpacks.filters.provider.label'),
        }, p.providers.map(prov => h('option', { key: prov.id, value: prov.id }, t(PROVIDER_LABEL_KEY(prov.id))))),

        p.caps?.minecraft_version_filter
            ? h('select', {
                key: 'mc',
                value: p.mcVersion,
                onChange: (e: { target: { value: string } }) => p.setMcVersion(e.target.value),
                style: C.select,
                'aria-label': t('modpacks.filters.minecraft_version.label'),
            }, [
                h('option', { key: '_', value: '' }, t('modpacks.filters.minecraft_version.all')),
                ...p.mcVersions.map(v => h('option', { key: v, value: v }, v)),
            ])
            : null,

        p.caps?.loader_filter
            ? h('select', {
                key: 'ld',
                value: p.loader,
                onChange: (e: { target: { value: string } }) => p.setLoader(e.target.value),
                style: C.select,
                'aria-label': t('modpacks.filters.loader.label'),
            }, [
                h('option', { key: '_', value: '' }, t('modpacks.filters.loader.all')),
                h('option', { key: 'forge', value: 'forge' }, t('modpacks.filters.loader.forge')),
                h('option', { key: 'fabric', value: 'fabric' }, t('modpacks.filters.loader.fabric')),
                h('option', { key: 'quilt', value: 'quilt' }, t('modpacks.filters.loader.quilt')),
                h('option', { key: 'neoforge', value: 'neoforge' }, t('modpacks.filters.loader.neoforge')),
            ])
            : null,

        // Sort dropdown — only the values declared by the active provider's
        // capabilities. Hidden when the provider exposes only one mode (or
        // none) since a 1-option dropdown is just visual noise.
        (p.caps?.sort_modes ?? []).length > 1
            ? h('select', {
                key: 'sort',
                value: p.sort,
                onChange: (e: { target: { value: string } }) => p.setSort(e.target.value),
                style: C.select,
                'aria-label': t('modpacks.filters.sort.label'),
            }, [
                h('option', { key: '_', value: '' }, t('modpacks.filters.sort.default')),
                ...((p.caps?.sort_modes ?? []).map(mode =>
                    h('option', { key: mode, value: mode }, t(`modpacks.filters.sort.${mode}`)))),
            ])
            : null,

        // Category dropdown — provider-conditional. Categories arrive
        // asynchronously (separate query) so we render a disabled
        // placeholder until the list is loaded.
        p.caps?.category_filter
            ? h('select', {
                key: 'cat',
                value: p.category,
                onChange: (e: { target: { value: string } }) => p.setCategory(e.target.value),
                style: C.select,
                disabled: p.categories.length === 0,
                'aria-label': t('modpacks.filters.category.label'),
            }, [
                h('option', { key: '_', value: '' }, t('modpacks.filters.category.all')),
                ...p.categories.map(c => h('option', { key: c.id, value: c.id }, c.label)),
            ])
            : null,

        h('div', { key: 'search', style: { display: 'flex', flex: 1, minWidth: 220, gap: '0.375rem' } }, [
            h('input', {
                key: 'i',
                type: 'text',
                value: p.searchTerm,
                placeholder: t('modpacks.filters.search_placeholder'),
                onChange: (e: { target: { value: string } }) => p.setSearchTerm(e.target.value),
                onKeyDown: (e: KeyboardEvent) => { if (e.key === 'Enter') p.commitSearch(); },
                style: C.input,
            }),
            h('button', { key: 'b', type: 'button', onClick: p.commitSearch, style: C.btnGhost },
                svg('M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z', 16)),
        ]),

        h('select', {
            key: 'size',
            value: String(p.pageSize),
            onChange: (e: { target: { value: string } }) => p.setPageSize(Number(e.target.value)),
            style: C.select,
            'aria-label': t('modpacks.filters.page_size.label'),
        }, PAGE_SIZES.map(n => h('option', { key: n, value: String(n) }, String(n)))),
    ]);
}

function renderMissingApiKey(provider: Provider, t: (k: string, o?: Record<string, unknown>) => string): ReturnType<typeof h> {
    return h('div', { key: 'noapi', style: C.bannerWarn }, [
        h('span', { key: 'i', style: { color: 'var(--color-warning, #f59e0b)' } },
            svg('M12 9v2m0 4h.01M10.29 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z')),
        h('div', { key: 't', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.25rem' } }, [
            h('p', { key: 'h', style: { margin: 0, fontSize: '0.875rem', fontWeight: 600 } },
                t('modpacks.errors.provider_not_configured')),
            h('p', { key: 'b', style: { margin: 0, fontSize: '0.75rem', color: 'var(--color-text-muted)' } },
                t('modpacks.missing_api_key.hint', { provider: provider.name })),
            provider.external_register_url
                ? h('a', { key: 'l', href: provider.external_register_url, target: '_blank', rel: 'noopener noreferrer', style: { fontSize: '0.75rem', color: 'var(--color-primary)' } },
                    t('modpacks.missing_api_key.go_register'))
                : null,
        ]),
    ]);
}

interface ResultsProps {
    hits: ModpackHit[];
    meta: SearchMeta | null;
    isLoading: boolean;
    isError: boolean;
    page: number;
    pageSize: number;
    setPage: (n: number) => void;
    canInstall: boolean;
    hasInstallActive: boolean;
    onInstall: (hit: ModpackHit) => void;
    serverMarkerSupported: boolean;
    t: (k: string, o?: Record<string, unknown>) => string;
}

function renderResults(p: ResultsProps): ReturnType<typeof h> {
    const t = p.t;

    if (p.isError) {
        return h('div', { key: 'err', style: C.bannerError }, [
            h('span', { key: 't' }, t('modpacks.errors.search_failed')),
        ]);
    }

    if (p.isLoading && p.hits.length === 0) {
        return h('div', { key: 'sk', style: C.grid },
            Array.from({ length: p.pageSize }).map((_, i) =>
                h('div', { key: i, style: { ...C.skeleton, background: 'var(--color-surface)', border: '1px solid var(--color-border)' }, className: 'skeleton-shimmer' })));
    }

    if (p.hits.length === 0) {
        return h('div', { key: 'empty', style: { ...C.glassCard, textAlign: 'center' as const, padding: '4rem 1rem' } }, [
            h('div', { key: 'i', style: { ...C.iconBox, margin: '0 auto 1rem', width: 56, height: 56 } },
                svg('M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z', 28)),
            h('p', { key: 't', style: { fontSize: '0.875rem', color: 'var(--color-text-secondary)', margin: 0 } },
                t('modpacks.empty.title')),
            h('p', { key: 'h', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)', marginTop: '0.5rem' } },
                t('modpacks.empty.description')),
        ]);
    }

    return h('div', { key: 'res', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.75rem' } }, [
        h('div', { key: 'grid', style: C.grid }, p.hits.map(hit => renderCard(hit, p.canInstall, p.hasInstallActive, p.onInstall, p.serverMarkerSupported, t))),

        p.meta && p.meta.last_page > 1
            ? h('div', { key: 'pag', style: C.pagination }, [
                h('p', { key: 'i', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)', margin: 0 } },
                    t('modpacks.pagination.indicator', { current: p.meta.current_page, total: p.meta.last_page })),
                h('div', { key: 'b', style: { display: 'flex', gap: '0.375rem' } }, [
                    h('button', { key: 'prev', type: 'button', disabled: p.page <= 1, onClick: () => p.setPage(p.page - 1), style: { ...C.btnGhost, opacity: p.page <= 1 ? 0.5 : 1 } },
                        t('modpacks.pagination.previous')),
                    h('button', { key: 'next', type: 'button', disabled: p.page >= (p.meta?.last_page ?? 1), onClick: () => p.setPage(p.page + 1), style: { ...C.btnGhost, opacity: p.page >= (p.meta?.last_page ?? 1) ? 0.5 : 1 } },
                        t('modpacks.pagination.next')),
                ]),
            ])
            : null,
    ]);
}

function renderCard(
    hit: ModpackHit,
    canInstall: boolean,
    installLocked: boolean,
    onInstall: (hit: ModpackHit) => void,
    serverMarkerSupported: boolean,
    t: (k: string, o?: Record<string, unknown>) => string,
): ReturnType<typeof h> {
    return h('div', {
        key: `${hit.provider}:${hit.modpack_id}`,
        style: { ...C.card, display: 'flex', flexDirection: 'column' as const, gap: '0.5rem' },
        className: 'mp-card',
    }, [
        hit.icon_url
            ? h('img', { key: 'thumb', src: hit.icon_url, alt: '', loading: 'lazy', style: C.cardThumb })
            : h('div', { key: 'thumb', style: { ...C.cardThumb, display: 'flex', alignItems: 'center', justifyContent: 'center', color: 'var(--color-text-muted)' } },
                svg('M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z', 36)),

        h('div', { key: 'top', style: { display: 'flex', alignItems: 'center', gap: '0.375rem', flexWrap: 'wrap' as const } }, [
            h('p', { key: 'n', style: C.cardName }, hit.name),
            serverMarkerSupported && hit.is_server_compatible
                ? h('span', { key: 'srv', style: C.badge('rgba(var(--color-success-rgb,16 185 129),0.12)', 'var(--color-success, #10b981)') },
                    t('modpacks.cards.server_compatible_badge'))
                : null,
        ]),

        hit.description ? h('p', { key: 'd', style: C.cardDesc }, hit.description) : null,

        h('div', { key: 'actions', style: { display: 'flex', justifyContent: 'space-between', gap: '0.5rem', marginTop: 'auto' } }, [
            hit.external_url
                ? h('a', { key: 'view', href: hit.external_url, target: '_blank', rel: 'noopener noreferrer', style: { ...C.btnGhost, textDecoration: 'none', fontSize: '0.75rem', padding: '0.375rem 0.625rem' } },
                    t('modpacks.cards.cta_view'))
                : h('span', { key: 'spacer' }),
            canInstall
                ? h('button', {
                    key: 'install', type: 'button',
                    onClick: () => onInstall(hit),
                    disabled: installLocked,
                    style: { ...C.btnPrimary, opacity: installLocked ? 0.5 : 1, fontSize: '0.75rem', padding: '0.375rem 0.75rem' },
                }, t('modpacks.cards.cta_install'))
                : null,
        ]),
    ]);
}

P.registerServerPage('modpacks', ModpacksPage);
P.register('minecraft-modpack-installer', () => null);
