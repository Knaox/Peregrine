import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useAdminServers } from '@/hooks/useAdminServers';
import { useAuthStore } from '@/stores/authStore';
import { useServersListLiveUpdates } from '@/hooks/useServersListLiveUpdates';
import { AdminServerRow } from '@/components/admin/AdminServerRow';
import { useNamespace } from '@/i18n/useNamespace';

const stagger = { animate: { transition: { staggerChildren: 0.04 } } };
const fadeUp = {
    initial: { opacity: 0, y: 12 },
    animate: { opacity: 1, y: 0, transition: { duration: 0.3, ease: [0.22, 1, 0.36, 1] as [number, number, number, number] } },
};

/**
 * Admin mode dashboard at /admin/servers — every server in the panel with its
 * owner. Styled to match the biome dashboard: a branded hero header with a
 * drifting halo + live total, a polished search/filter bar and staggered rows.
 */
export function AdminServersPage() {
    useNamespace(["admin-servers-spa", "server-overview"] as const);
    const { t } = useTranslation();
    const { user } = useAuthStore();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading, isError } = useAdminServers({ search, status, page, per_page: 25 });
    useServersListLiveUpdates({ userId: user?.id ?? null, isAdmin: Boolean(user?.is_admin) });

    if (user === null) return null;

    if (!user.is_admin) {
        return (
            <div className="p-8">
                <div className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
                    {t('common:errors.no_permission')}
                </div>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-5xl space-y-5 p-4 sm:p-6">
            {/* Hero header */}
            <m.div initial={{ opacity: 0, y: -12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.45, ease: 'easeOut' }}
                className="relative overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)]/50 px-5 py-6 backdrop-blur-xl sm:px-7">
                <div aria-hidden className="biome-hero-halo pointer-events-none absolute -right-20 -top-24 h-64 w-64" />
                <div className="relative z-10 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="biome-shimmer-text text-2xl font-extrabold tracking-tight sm:text-3xl">
                            {t('admin-servers-spa:servers.title')}
                        </h1>
                        <p className="mt-1.5 text-sm text-[var(--color-text-secondary)]">
                            {t('admin-servers-spa:servers.subtitle')}
                        </p>
                    </div>
                    {data?.meta !== undefined && (
                        <div className="flex items-center gap-2.5 rounded-[var(--radius-md)] border border-[var(--color-border)]/70 bg-[var(--color-surface)]/60 px-3.5 py-2 backdrop-blur-md">
                            <span className="h-2.5 w-2.5 rounded-full" style={{ background: 'var(--color-primary)', boxShadow: '0 0 10px var(--color-primary)' }} />
                            <div className="flex flex-col leading-none">
                                <span className="font-mono text-lg font-bold tabular-nums text-[var(--color-text-primary)]">{data.meta.total}</span>
                                <span className="mt-0.5 text-[10px] font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">
                                    {t('admin-servers-spa:servers.title')}
                                </span>
                            </div>
                        </div>
                    )}
                </div>
            </m.div>

            {/* Search + filter */}
            <div className="flex flex-col gap-3 sm:flex-row">
                <div className="relative flex-1">
                    <svg className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--color-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                        <circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" strokeLinecap="round" />
                    </svg>
                    <input
                        type="search"
                        placeholder={t('admin-servers-spa:servers.search_placeholder')}
                        value={search}
                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                        className="w-full rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface)] py-2.5 pl-9 pr-3 text-sm text-[var(--color-text-primary)] transition-colors focus:border-[var(--color-primary)] focus:outline-none"
                    />
                </div>
                <select
                    value={status}
                    onChange={(e) => { setStatus(e.target.value); setPage(1); }}
                    className="rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-2.5 text-sm text-[var(--color-text-primary)] transition-colors focus:border-[var(--color-primary)] focus:outline-none"
                >
                    <option value="">{t('admin-servers-spa:servers.filter.any_status')}</option>
                    <option value="active">{t('server-overview:status.active')}</option>
                    <option value="suspended">{t('server-overview:status.suspended')}</option>
                    <option value="terminated">{t('server-overview:status.terminated')}</option>
                </select>
            </div>

            {isLoading && (
                <div className="space-y-2">
                    {Array.from({ length: 6 }).map((_, i) => (
                        <div key={i} className="h-[68px] rounded-[var(--radius-lg)] border border-[var(--color-border)] skeleton-shimmer" />
                    ))}
                </div>
            )}

            {isError && (
                <div className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
                    {t('common:error')}
                </div>
            )}

            {data !== undefined && (
                <>
                    {data.data.length === 0 ? (
                        <p className="py-12 text-center text-sm text-[var(--color-text-muted)]">
                            {t('admin-servers-spa:servers.empty')}
                        </p>
                    ) : (
                        <m.div variants={stagger} initial="initial" animate="animate" className="space-y-2">
                            {data.data.map((server) => (
                                <m.div key={server.id} variants={fadeUp}>
                                    <AdminServerRow server={server} />
                                </m.div>
                            ))}
                        </m.div>
                    )}

                    {data.meta.last_page > 1 && (
                        <div className="flex items-center justify-center gap-2 pt-4">
                            <button type="button" onClick={() => setPage((p) => Math.max(1, p - 1))} disabled={page === 1}
                                className="rounded-[var(--radius-md)] border border-[var(--color-border)] px-3 py-1.5 text-sm text-[var(--color-text-secondary)] transition-colors hover:border-[var(--color-primary)]/50 disabled:opacity-50 cursor-pointer">
                                {t('common:previous')}
                            </button>
                            <span className="text-sm text-[var(--color-text-muted)]">{page} / {data.meta.last_page}</span>
                            <button type="button" onClick={() => setPage((p) => Math.min(data.meta.last_page, p + 1))} disabled={page === data.meta.last_page}
                                className="rounded-[var(--radius-md)] border border-[var(--color-border)] px-3 py-1.5 text-sm text-[var(--color-text-secondary)] transition-colors hover:border-[var(--color-primary)]/50 disabled:opacity-50 cursor-pointer">
                                {t('common:next')}
                            </button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
