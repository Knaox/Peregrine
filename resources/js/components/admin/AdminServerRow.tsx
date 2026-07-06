import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import type { AdminServer } from '@/types/AdminServer';
import { useNamespace } from '@/i18n/useNamespace';

interface AdminServerRowProps {
    server: AdminServer;
}

function statusColor(status: string): string {
    if (status === 'active' || status === 'running') return 'var(--color-success)';
    if (status === 'suspended') return 'var(--color-warning)';
    if (status === 'terminated' || status === 'provisioning_failed') return 'var(--color-danger)';
    if (status === 'provisioning') return 'var(--color-info)';
    return 'var(--color-text-muted)';
}

const NODE_SEVERITY_COLORS: Record<string, string> = {
    ok: 'var(--color-success)',
    warning: 'var(--color-warning)',
    critical: 'var(--color-danger)',
};

/**
 * Modern admin servers row — egg thumbnail + status orb, server identity,
 * owner (avatar + email) and a status pill. Full-row link opens the server.
 * Theme-token driven; hover lifts + glows to match the biome dashboard.
 */
export function AdminServerRow({ server }: AdminServerRowProps) {
    useNamespace(["server-overview", "server-shell"] as const);
    const { t } = useTranslation();
    const color = statusColor(server.status);
    const banner = server.egg?.banner_image ?? null;
    const ownerInitial = server.owner.name?.trim().charAt(0).toUpperCase() || '?';
    const nodeColor = server.node?.health
        ? (NODE_SEVERITY_COLORS[server.node.health.severity] ?? 'var(--color-text-muted)')
        : 'var(--color-text-muted)';

    return (
        <Link
            to={`/servers/${server.id}`}
            className="group flex items-center gap-3 rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)] p-2.5 sm:p-3 transition-[transform,border-color,box-shadow] duration-200 hover:-translate-y-0.5 hover:border-[var(--color-primary)]/60 hover:shadow-[0_12px_30px_-14px_var(--color-primary-glow)]"
        >
            {/* Egg thumbnail + status orb */}
            <div className="relative h-11 w-11 shrink-0 overflow-hidden rounded-[var(--radius-md)] border border-[var(--color-border)]">
                {banner ? (
                    <img src={banner} alt="" aria-hidden className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110" />
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-sm font-bold text-[var(--color-primary)]"
                        style={{ background: 'linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 22%, var(--color-surface-elevated)), var(--color-surface))' }}>
                        {(server.egg?.name ?? server.name).charAt(0).toUpperCase()}
                    </div>
                )}
                <span className="absolute bottom-0.5 right-0.5 h-2.5 w-2.5 rounded-full ring-2 ring-[var(--color-surface)]" style={{ background: color, boxShadow: `0 0 6px ${color}` }} />
            </div>

            {/* Name + identifier · egg */}
            <div className="min-w-0 flex-1">
                <div className="truncate font-semibold text-[var(--color-text-primary)] transition-colors group-hover:text-[var(--color-primary)]">
                    {server.name}
                </div>
                <div className="mt-0.5 truncate text-xs text-[var(--color-text-muted)]">
                    <span className="font-mono">{server.identifier ?? '—'}</span>
                    {server.egg && <><span className="mx-1.5">·</span><span>{server.egg.name}</span></>}
                </div>
            </div>

            {/* Owner */}
            <div className="hidden w-52 min-w-0 items-center gap-2 sm:flex">
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-[var(--color-primary)]"
                    style={{ background: 'rgba(var(--color-primary-rgb), 0.14)' }}>
                    {ownerInitial}
                </span>
                <div className="min-w-0">
                    <div className="truncate text-sm text-[var(--color-text-secondary)]">{server.owner.name}</div>
                    <div className="truncate text-xs text-[var(--color-text-muted)]">{server.owner.email}</div>
                </div>
            </div>

            {/* Hosting node + cached Wings health dot */}
            {server.node && (
                <div
                    className="hidden w-36 min-w-0 items-center gap-1.5 md:flex"
                    title={server.node.health
                        ? t(`server-shell:detail.node_status.${server.node.health.status}`)
                        : t('server-shell:detail.node_status.unknown')}
                >
                    <span
                        className="h-2 w-2 shrink-0 rounded-full"
                        style={{ background: nodeColor, boxShadow: server.node.health ? `0 0 6px ${nodeColor}` : undefined }}
                    />
                    <span className="truncate text-xs text-[var(--color-text-secondary)]">{server.node.name}</span>
                </div>
            )}

            {/* Status pill */}
            <span className="shrink-0 rounded-[var(--radius-full)] px-2.5 py-0.5 text-xs font-semibold"
                style={{ background: `color-mix(in srgb, ${color} 16%, transparent)`, color }}>
                {t(`server-overview:status.${server.status}`, { defaultValue: server.status })}
            </span>

            <svg className="hidden h-4 w-4 shrink-0 text-[var(--color-text-muted)] opacity-0 transition-opacity group-hover:opacity-100 sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </Link>
    );
}
