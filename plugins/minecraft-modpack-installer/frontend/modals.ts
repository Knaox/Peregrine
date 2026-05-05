/**
 * Install + uninstall modals for the modpack installer plugin. Pure CSS
 * animation (no motion lib) — fade scrim + scale-in card via inline styles.
 */
import { C, h, S, svg, type ModpackVersion } from './shared';

const { useState } = S.React;

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
    /** Pre-filtered MC version, used as a hint label only. */
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

    const versions = p.versions ?? [];
    const versionsForRender = p.minecraftVersionFilter !== null
        ? versions.filter(v => v.minecraft_versions.length === 0 || v.minecraft_versions.includes(p.minecraftVersionFilter as string))
        : versions;

    const labelOption = (v: ModpackVersion) => {
        const mc = v.minecraft_versions.length > 0 ? ` — MC ${v.minecraft_versions.join(', ')}` : '';
        const loaders = v.loaders.length > 0 ? ` [${v.loaders.join(', ')}]` : '';
        return `${v.label}${mc}${loaders}`;
    };

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

        p.isLoadingVersions
            ? h('p', { key: 'loading', style: { fontSize: '0.8125rem', color: 'var(--color-text-muted)' } }, t('modpacks.install_modal.loading_versions'))
            : h('div', { key: 'version-row', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.375rem' } }, [
                h('label', { key: 'l', style: { fontSize: '0.75rem', fontWeight: 500, color: 'var(--color-text-secondary)' } }, t('modpacks.install_modal.version_label')),
                h('select', {
                    key: 's',
                    value: versionId,
                    onChange: (e: { target: { value: string } }) => setVersionId(e.target.value),
                    style: { ...C.select, width: '100%' },
                    disabled: versionsForRender.length === 0,
                }, [
                    h('option', { key: '_', value: '' }, t('modpacks.install_modal.version_placeholder')),
                    ...versionsForRender.map(v => h('option', { key: v.version_id, value: v.version_id }, labelOption(v))),
                ]),
                versionsForRender.length === 0
                    ? h('p', { key: 'empty', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)', margin: 0 } }, t('modpacks.install_modal.no_versions'))
                    : null,
            ]),

        h('label', { key: 'purge-row', style: { display: 'flex', alignItems: 'flex-start', gap: '0.5rem', cursor: 'pointer' } }, [
            h('input', { key: 'cb', type: 'checkbox', checked: purge, onChange: () => setPurge(!purge), style: { marginTop: 4 } }),
            h('div', { key: 'txt', style: { display: 'flex', flexDirection: 'column' as const, gap: '0.125rem' } }, [
                h('span', { key: 'l', style: { fontSize: '0.8125rem', fontWeight: 500, color: 'var(--color-text-primary)' } }, t('modpacks.install_modal.purge.label')),
                h('span', { key: 'h', style: { fontSize: '0.75rem', color: 'var(--color-text-muted)' } }, t('modpacks.install_modal.purge.help')),
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
