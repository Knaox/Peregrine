import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import type { AdminServer } from '@/types/AdminServer';

interface AdminServerRowProps {
    server: AdminServer;
}

/**
 * Flat row in the admin servers table. Clicking the server name navigates to
 * /servers/:id/console (same route as the user-facing flow — policy bypass
 * lets admins open any server).
 */
export function AdminServerRow({ server }: AdminServerRowProps) {
    const { t } = useTranslation();

    return (
        <div className="flex items-center gap-4 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface)] p-3 transition-colors hover:border-[var(--color-border-hover)]">
            <div className="min-w-0 flex-1">
                <Link
                    to={`/servers/${server.id}`}
                    className="truncate font-medium text-[var(--color-text-primary)] hover:text-[var(--color-primary)] transition-colors"
                >
                    {server.name}
                </Link>
                <div className="mt-0.5 truncate text-xs text-[var(--color-text-muted)]">
                    <span className="font-mono">{server.identifier ?? '—'}</span>
                    {server.egg !== undefined && server.egg !== null && (
                        <>
                            <span className="mx-1.5">·</span>
                            <span>{server.egg.name}</span>
                        </>
                    )}
                </div>
            </div>

            <div className="w-48 min-w-0 text-right">
                <div className="truncate text-sm text-[var(--color-text-secondary)]">
                    {server.owner.name}
                </div>
                <div className="truncate text-xs text-[var(--color-text-muted)]">{server.owner.email}</div>
            </div>

            <span
                className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                    server.status === 'active'
                        ? 'bg-[var(--color-success)]/15 text-[var(--color-success)]'
                        : server.status === 'suspended'
                            ? 'bg-[var(--color-warning)]/15 text-[var(--color-warning)]'
                            : 'bg-[var(--color-surface-elevated)] text-[var(--color-text-muted)]'
                }`}
            >
                {t(`servers.status.${server.status}`, { defaultValue: server.status })}
            </span>
        </div>
    );
}
