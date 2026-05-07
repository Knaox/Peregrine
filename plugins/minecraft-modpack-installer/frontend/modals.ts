/**
 * Install + uninstall modals for the modpack installer plugin.
 *
 * The install modal is the user's last off-ramp before they wipe a server,
 * so it has to:
 *
 *  - Show **every** modpack version the provider exposes — not a paginated
 *    slice. Provider-side pagination (CurseForge's 50-per-page cap) is
 *    handled in PHP; here we just render whatever the API returns.
 *  - Make the Minecraft version each option targets impossible to miss.
 *  - Let the user narrow by Minecraft version *inside* the modal so they
 *    don't have to close the modal, change the global filter, and reopen.
 */
import { C, h, S, svg, type ModpackVersion } from './shared';

const { useState, useMemo } = S.React;

export interface InstallModalProps {
    open: boolean;
    /**
     * `t` is provided by the parent that calls useTranslation unconditionally.
     * Calling useTranslation in this conditional render path would change the
     * parent's hook count when `open` toggles → React #310.
     */
    t: (k: string, o?: Record<string, unknown>) => string;
    modpackName: string;
    versions: ModpackVersion[] | null;
    isLoadingVersions: boolean;
    isSubmitting: boolean;
    onCancel: () => void;
    onConfirm: (versionId: string, purgeFiles: boolean) => void;
    error: string | null;
    /** Pre-filtered MC version inherited from the marketplace filter bar. */
    minecraftVersionFilter: string | null;
}

export function renderInstallModal(p: InstallModalProps): ReturnType<typeof h> | null {
    if (!p.open) return null;
    return h(InstallModalInner, p);
}

function InstallModalInner(p: InstallModalProps & { t: (k: string, o?: Record<string, unknown>) => string }) {
    const { t } = p;
    const [versionId, setVersionId] = useState<string>('');
    const [purge, setPurge] = useState<boolean>(false);
    // Local MC selector — defaults to the bar's filter if any. Empty string
    // means "any MC version" (no filtering applied locally).
    const [mcFilter, setMcFilter] = useState<string>(p.minecraftVersionFilter ?? '');

    const allVersions = p.versions ?? [];

    // Aggregate every MC version mentioned by any returned modpack version
    // — this is the dropdown the user uses to find a compatible release.
    // Sort newest-first by semver, falling back to string ordering.
    const mcOptions = useMemo<string[]>(() => {
        const set = new Set<string>();
        for (const v of allVersions) {
            for (const mc of v.minecraft_versions) {
                if (mc) set.add(mc);
            }
        }
        return Array.from(set).sort((a, b) => versionCompareDesc(a, b));
    }, [allVersions]);

    const isCompatible = (v: ModpackVersion) =>
        mcFilter === ''
        || v.minecraft_versions.length === 0
        || v.minecraft_versions.includes(mcFilter);

    // Two-stage rendering: compatible first (sorted by release-type), then
    // incompatible greyed-out so the user still sees them and understands
    // *why* they're not pickable. We still allow selection of incompatible
    // ones — the panel only warns; it never overrides user intent.
    const orderedVersions = useMemo<ModpackVersion[]>(() => {
        const compat = allVersions.filter(isCompatible);
        const incompat = allVersions.filter(v => !isCompatible(v));
        return [...compat, ...incompat];
    }, [allVersions, mcFilter]);

    // Default the selection to the most recent compatible release whenever
    // the version list (or the MC filter) changes — so the confirm button
    // is enabled out-of-the-box when there's an obvious "best" candidate.
    if (versionId === '' && orderedVersions.length > 0) {
        const firstCompatible = orderedVersions.find(isCompatible);
        if (firstCompatible) {
            queueMicrotask(() => setVersionId(firstCompatible.version_id));
        }
    }

    const selected = orderedVersions.find(v => v.version_id === versionId) ?? null;

    return h('div', {
        style: C.modalScrim,
        onClick: p.onCancel,
        className: 'mp-modal-scrim',
    }, h('div', {
        style: C.modalCard,
        onClick: (e: Event) => e.stopPropagation(),
        className: 'mp-modal-card',
    }, [
        h('h3', { key: 'title', style: { margin: 0, fontSize: '1.0625rem', fontWeight: 700, color: 'var(--color-text-primary)' } },
            t('modpacks.install_modal.title', { name: p.modpackName, defaultValue: `Install ${p.modpackName}` })),

        h('div', { key: 'warning', style: C.bannerWarn }, [
            h('span', { key: 'i', style: { color: 'var(--color-warning, #f59e0b)' } }, svg('M12 9v2m0 4h.01M10.29 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z')),
            h('span', { key: 't', style: { fontSize: '0.8125rem' } }, t('modpacks.install_modal.warning_world')),
        ]),

        // ---- Minecraft version filter row -----------------------------
        mcOptions.length > 0
            ? h('div', { key: 'mc-row', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.375rem' } }, [
                h('label', { key: 'l', style: { fontSize: '0.75rem', fontWeight: 500, color: 'var(--color-text-secondary)' } },
                    t('modpacks.install_modal.mc_filter_label')),
                h('div', { key: 'sel', style: { display: 'flex', gap: '0.375rem', alignItems: 'center', flexWrap: 'wrap' as const } }, [
                    h('select', {
                        key: 's',
                        value: mcFilter,
                        onChange: (e: { target: { value: string } }) => {
                            setMcFilter(e.target.value);
                            setVersionId('');  // re-run the auto-pick for the new filter
                        },
                        style: { ...C.select, flex: '1 1 auto', minWidth: 160 },
                    }, [
                        h('option', { key: '_', value: '' }, t('modpacks.install_modal.mc_filter_any')),
                        ...mcOptions.map(v => h('option', { key: v, value: v }, v)),
                    ]),
                    h('span', { key: 'count', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)' } },
                        t('modpacks.install_modal.mc_filter_count', {
                            compat: orderedVersions.filter(isCompatible).length,
                            total: allVersions.length,
                        })),
                ]),
            ])
            : null,

        // ---- Version list ---------------------------------------------
        p.isLoadingVersions
            ? h('p', { key: 'loading', style: { fontSize: '0.8125rem', color: 'var(--color-text-muted)' } },
                t('modpacks.install_modal.loading_versions'))
            : orderedVersions.length === 0
                ? h('p', { key: 'empty', style: { fontSize: '0.8125rem', color: 'var(--color-text-muted)' } },
                    t('modpacks.install_modal.no_versions'))
                : h('div', { key: 'list', style: C.versionList },
                    orderedVersions.map(v => renderVersionRow(v, versionId, isCompatible(v), () => setVersionId(v.version_id), t))),

        // ---- Selected version summary ----------------------------------
        selected
            ? h('div', { key: 'selected', style: {
                display: 'flex', flexWrap: 'wrap' as const, gap: '0.375rem',
                padding: '0.625rem 0.75rem',
                borderRadius: 'var(--radius)',
                border: '1px solid var(--color-border)',
                background: 'var(--color-background)',
                fontSize: '0.75rem',
                color: 'var(--color-text-secondary)',
            } }, [
                h('span', { key: 'l', style: { color: 'var(--color-text-muted)' } },
                    t('modpacks.install_modal.selection_label')),
                h('strong', { key: 'lbl', style: { color: 'var(--color-text-primary)' } }, selected.label),
                selected.minecraft_versions.length > 0
                    ? h('span', {
                        key: 'mc',
                        style: C.badge('rgba(var(--color-primary-rgb),0.12)', 'var(--color-primary)'),
                    }, `MC ${selected.minecraft_versions.join(', ')}`)
                    : null,
                ...selected.loaders.map(l => h('span', {
                    key: `ld-${l}`,
                    style: C.badge('rgba(var(--color-info-rgb,59 130 246),0.10)', 'var(--color-info, #3b82f6)'),
                }, l)),
                h('span', { key: 'rt', style: { color: 'var(--color-text-muted)' } }, selected.release_type),
            ])
            : null,

        // ---- Purge toggle ---------------------------------------------
        h('label', { key: 'purge-row', style: { display: 'flex', alignItems: 'flex-start', gap: '0.5rem', cursor: 'pointer' } }, [
            h('input', { key: 'cb', type: 'checkbox', checked: purge, onChange: () => setPurge(!purge), style: { marginTop: 4 } }),
            h('div', { key: 'txt', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.125rem' } }, [
                h('span', { key: 'l', style: { fontSize: '0.8125rem', fontWeight: 500, color: 'var(--color-text-primary)' } },
                    t('modpacks.install_modal.purge.label')),
                h('span', { key: 'h', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)' } },
                    t('modpacks.install_modal.purge.help')),
            ]),
        ]),

        p.error ? h('p', { key: 'err', style: { fontSize: '0.75rem', color: 'var(--color-danger)', margin: 0 } }, p.error) : null,

        h('div', { key: 'actions', style: { display: 'flex', justifyContent: 'flex-end', gap: '0.5rem', marginTop: '0.25rem' } }, [
            h('button', {
                key: 'cancel', type: 'button', onClick: p.onCancel,
                style: C.btnGhost,
                disabled: p.isSubmitting,
            }, t('modpacks.install_modal.cancel')),
            h('button', {
                key: 'confirm', type: 'button',
                onClick: () => p.onConfirm(versionId, purge),
                style: { ...C.btnPrimary, opacity: !versionId || p.isSubmitting ? 0.5 : 1 },
                disabled: !versionId || p.isSubmitting,
            }, p.isSubmitting ? t('modpacks.install_modal.submitting') : t('modpacks.install_modal.confirm')),
        ]),
    ]));
}

function renderVersionRow(
    v: ModpackVersion,
    selectedId: string,
    compatible: boolean,
    onSelect: () => void,
    t: (k: string, o?: Record<string, unknown>) => string,
): ReturnType<typeof h> {
    const isSelected = v.version_id === selectedId;
    const releaseColor = v.release_type === 'release'
        ? 'var(--color-success, #10b981)'
        : v.release_type === 'beta'
            ? 'var(--color-warning, #f59e0b)'
            : v.release_type === 'alpha'
                ? 'var(--color-danger, #ef4444)'
                : 'var(--color-text-muted)';

    return h('div', {
        key: v.version_id,
        role: 'button',
        tabIndex: 0,
        onClick: onSelect,
        onKeyDown: (e: KeyboardEvent) => { if (e.key === 'Enter' || e.key === ' ') { onSelect(); e.preventDefault(); } },
        style: C.versionRow(isSelected, compatible),
    }, [
        h('input', {
            key: 'r',
            type: 'radio',
            checked: isSelected,
            readOnly: true,
            tabIndex: -1,
            style: { margin: 0, pointerEvents: 'none' as const },
        }),
        h('div', { key: 'main', style: { flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' as const, gap: '0.125rem' } }, [
            h('div', { key: 'top', style: { display: 'flex', gap: '0.375rem', alignItems: 'center', flexWrap: 'wrap' as const } }, [
                h('span', {
                    key: 'lbl',
                    style: {
                        fontSize: '0.8125rem', fontWeight: 600,
                        color: 'var(--color-text-primary)',
                        overflow: 'hidden' as const, textOverflow: 'ellipsis' as const,
                        whiteSpace: 'nowrap' as const,
                        maxWidth: '100%',
                    },
                }, v.label || v.version_id),
                v.release_type !== 'release' && v.release_type !== 'unknown'
                    ? h('span', {
                        key: 'rt',
                        style: { fontSize: '0.625rem', fontWeight: 600, textTransform: 'uppercase' as const, color: releaseColor },
                    }, v.release_type)
                    : null,
                !compatible
                    ? h('span', {
                        key: 'incompat',
                        style: C.badge('rgba(var(--color-warning-rgb,245 158 11),0.12)', 'var(--color-warning, #f59e0b)'),
                    }, t('modpacks.install_modal.incompatible_badge'))
                    : null,
            ]),
            h('div', { key: 'meta', style: { display: 'flex', gap: '0.375rem', flexWrap: 'wrap' as const, fontSize: '0.6875rem', color: 'var(--color-text-muted)' } }, [
                v.minecraft_versions.length > 0
                    ? h('span', {
                        key: 'mc',
                        style: {
                            fontWeight: 600,
                            color: compatible ? 'var(--color-primary)' : 'var(--color-text-muted)',
                        },
                    }, `MC ${v.minecraft_versions.join(', ')}`)
                    : h('span', { key: 'mc', style: { fontStyle: 'italic' as const } }, t('modpacks.install_modal.mc_unknown')),
                ...v.loaders.map(l => h('span', { key: `ld-${l}` }, `· ${l}`)),
            ]),
        ]),
    ]);
}

/** Best-effort newest-first ordering. Falls back to string comparison. */
function versionCompareDesc(a: string, b: string): number {
    const ra = a.split('.').map(n => parseInt(n, 10));
    const rb = b.split('.').map(n => parseInt(n, 10));
    const len = Math.max(ra.length, rb.length);
    for (let i = 0; i < len; i++) {
        const da = ra[i] ?? 0;
        const db = rb[i] ?? 0;
        if (Number.isNaN(da) || Number.isNaN(db)) return a < b ? 1 : a > b ? -1 : 0;
        if (da !== db) return db - da;
    }
    return 0;
}

// ---------------------------------------------------------------------------
// Uninstall confirmation
// ---------------------------------------------------------------------------

export interface UninstallModalProps {
    open: boolean;
    /** Same hook-safety contract as InstallModalProps.t. */
    t: (k: string, o?: Record<string, unknown>) => string;
    modpackName: string;
    isSubmitting: boolean;
    onCancel: () => void;
    onConfirm: () => void;
    error: string | null;
}

export function renderUninstallModal(p: UninstallModalProps): ReturnType<typeof h> | null {
    if (!p.open) return null;
    const { t } = p;

    return h('div', {
        style: C.modalScrim,
        onClick: p.onCancel,
        className: 'mp-modal-scrim',
    }, h('div', {
        style: C.modalCard,
        onClick: (e: Event) => e.stopPropagation(),
        className: 'mp-modal-card',
    }, [
        h('h3', { key: 'title', style: { margin: 0, fontSize: '1.0625rem', fontWeight: 700, color: 'var(--color-text-primary)' } },
            t('modpacks.uninstall_modal.title', { name: p.modpackName, defaultValue: `Uninstall ${p.modpackName}?` })),

        h('div', { key: 'w', style: C.bannerError }, [
            h('span', { key: 'i', style: { color: 'var(--color-danger)' } }, svg('M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2')),
            h('span', { key: 't', style: { fontSize: '0.8125rem' } }, t('modpacks.uninstall_modal.warning')),
        ]),

        p.error ? h('p', { key: 'err', style: { fontSize: '0.75rem', color: 'var(--color-danger)', margin: 0 } }, p.error) : null,

        h('div', { key: 'actions', style: { display: 'flex', justifyContent: 'flex-end', gap: '0.5rem' } }, [
            h('button', { key: 'cancel', type: 'button', onClick: p.onCancel, style: C.btnGhost, disabled: p.isSubmitting },
                t('modpacks.uninstall_modal.cancel')),
            h('button', {
                key: 'confirm', type: 'button',
                onClick: p.onConfirm,
                style: { ...C.btnDanger, opacity: p.isSubmitting ? 0.5 : 1 },
                disabled: p.isSubmitting,
            }, p.isSubmitting ? t('modpacks.uninstall_modal.submitting') : t('modpacks.uninstall_modal.confirm')),
        ]),
    ]));
}
