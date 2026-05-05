import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useAdminServers } from '@/hooks/useAdminServers';
import { useAuthStore } from '@/stores/authStore';
import { useServersListLiveUpdates } from '@/hooks/useServersListLiveUpdates';
import { AdminServerRow } from '@/components/admin/AdminServerRow';

/**
 * Admin mode dashboard at /admin/servers — shows every server in the panel
 * with its owner. Access gated by the ProtectedRoute wrapper + server-side
 * `admin` + `two-factor` middleware. Non-admins see an explicit denial.
 */
export function AdminServersPage() {
    const { t } = useTranslation();
    const { user } = useAuthStore();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [page, setPage] = useState(1);

    const { data, isLoading, isError } = useAdminServers({
        search,
        status,
        page,
        per_page: 25,
    });
    useServersListLiveUpdates({ userId: user?.id ?? null, isAdmin: Boolean(user?.is_admin) });

    if (user === null) {
        return null;
    }

    if (! user.is_admin) {
        return (
            <div className="p-8">
                <div className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
                    {t('errors.no_permission')}
                </div>
            </div>
        );
    }

    return (
        <div className="mx-auto max-w-5xl space-y-5 p-6">
            <header className="flex items-end justify-between">
                <div>
                    <h1 className="text-2xl font-semibold text-[var(--color-text-primary)]">
                        {t('admin.servers.title')}
                    </h1>
                    <p className="mt-1 text-sm text-[var(--color-text-muted)]">
                        {t('admin.servers.subtitle')}
                    </p>
                </div>
                {data?.meta !== undefined && (
                    <span className="text-sm text-[var(--color-text-muted)]">
                        {t('admin.servers.count', { count: data.meta.total })}
                    </span>
                )}
            </header>

            <div className="flex gap-3">
                <input
                    type="search"
                    placeholder={t('admin.servers.search_placeholder')}
                    value={search}
                    onChange={(e) => {
                        setSearch(e.target.value);
                        setPage(1);
                    }}
                    className="flex-1 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-background)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:outline-none focus:ring-1 focus:border-[var(--color-primary)]"
                />
                <select
                    value={status}
                    onChange={(e) => {
                        setStatus(e.target.value);
                        setPage(1);
                    }}
                    className="rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-background)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:outline-none focus:ring-1 focus:border-[var(--color-primary)]"
                >
                    <option value="">{t('admin.servers.filter.any_status')}</option>
                    <option value="active">{t('servers.status.active')}</option>
                    <option value="suspended">{t('servers.status.suspended')}</option>
                    <option value="terminated">{t('servers.status.terminated')}</option>
                </select>
            </div>

            {isLoading && (
                <p className="text-sm text-[var(--color-text-muted)]">{t('common.loading')}</p>
            )}

            {isError && (
                <div className="rounded-[var(--radius)] border border-[var(--color-danger)]/30 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
                    {t('common.error')}
                </div>
            )}

            {data !== undefined && (
                <>
                    {data.data.length === 0 ? (
                        <p className="text-center text-sm text-[var(--color-text-muted)] py-12">
                            {t('admin.servers.empty')}
                        </p>
                    ) : (
                        <div className="space-y-2">
                            {data.data.map((server) => (
                                <AdminServerRow key={server.id} server={server} />
                            ))}
                        </div>
                    )}

                    {data.meta.last_page > 1 && (
                        <div className="flex items-center justify-center gap-2 pt-4">
                            <button
                                type="button"
                                onClick={() => setPage((p) => Math.max(1, p - 1))}
                                disabled={page === 1}
                                className="rounded-[var(--radius)] border border-[var(--color-border)] px-3 py-1.5 text-sm text-[var(--color-text-secondary)] disabled:opacity-50 cursor-pointer"
                            >
                                {t('common.previous')}
                            </button>
                            <span className="text-sm text-[var(--color-text-muted)]">
                                {page} / {data.meta.last_page}
                            </span>
                            <button
                                type="button"
                                onClick={() => setPage((p) => Math.min(data.meta.last_page, p + 1))}
                                disabled={page === data.meta.last_page}
                                className="rounded-[var(--radius)] border border-[var(--color-border)] px-3 py-1.5 text-sm text-[var(--color-text-secondary)] disabled:opacity-50 cursor-pointer"
                            >
                                {t('common.next')}
                            </button>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
