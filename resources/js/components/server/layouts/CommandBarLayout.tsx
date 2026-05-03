import { memo, useCallback, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import { copyToClipboard } from '@/utils/clipboard';
import { formatBytes, formatCpu, formatUptime } from '@/utils/format';
import { fetchServer } from '@/services/serverApi';
import { ServerCardPowerButtons } from '@/components/server/ServerCardPowerButtons';
import { resolveHealthColor } from '@/components/server/layouts/serverHealth';
import type { CardConfig } from '@/hooks/useCardConfig';
import type { PowerSignal } from '@/types/PowerSignal';
import type { Server } from '@/types/Server';
import type { ServerStats, ServerStatsMap } from '@/types/ServerStats';

interface CommandBarLayoutProps {
    servers: Server[];
    statsMap: ServerStatsMap | undefined;
    cardConfig: CardConfig;
    isSelectionMode: boolean;
    isSelected: (id: number) => boolean;
    onSelect: (id: number) => void;
    onPower: (serverId: number, signal: PowerSignal) => void;
    isPowerPending: boolean;
}

/**
 * Linear/Vercel-style dense list. Each server is a single 56px row with a
 * coloured 3-px left bar (status), name + meta, IP pill, CPU/RAM bars and
 * hover-revealed quick actions on the right. Optimised for hosters with
 * many servers — far higher info density than the classic card grid.
 *
 * Categories from `useDashboardLayout` are intentionally ignored — this
 * variant is a flat list (the help text in /theme-studio surfaces this).
 */
function CommandBarLayoutImpl({
    servers,
    statsMap,
    cardConfig,
    isSelectionMode,
    isSelected,
    onSelect,
    onPower,
    isPowerPending,
}: CommandBarLayoutProps) {
    return (
        <div className="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--color-border)]/50 bg-[var(--color-surface)]/40 backdrop-blur-sm">
            {servers.map((server, idx) => (
                <CommandBarRow
                    key={server.id}
                    server={server}
                    stats={statsMap?.[server.id]}
                    cardConfig={cardConfig}
                    isFirst={idx === 0}
                    isSelectionMode={isSelectionMode}
                    isSelected={isSelected(server.id)}
                    onSelect={onSelect}
                    onPower={onPower}
                    isPowerPending={isPowerPending}
                />
            ))}
        </div>
    );
}

interface CommandBarRowProps {
    server: Server;
    stats: ServerStats | undefined;
    cardConfig: CardConfig;
    isFirst: boolean;
    isSelectionMode: boolean;
    isSelected: boolean;
    onSelect: (id: number) => void;
    onPower: (serverId: number, signal: PowerSignal) => void;
    isPowerPending: boolean;
}

function CommandBarRowImpl({
    server,
    stats,
    cardConfig,
    isFirst,
    isSelectionMode,
    isSelected,
    onSelect,
    onPower,
    isPowerPending,
}: CommandBarRowProps) {
    const navigate = useNavigate();
    const { t } = useTranslation();
    const queryClient = useQueryClient();
    const [copied, setCopied] = useState(false);

    const lifecycleStatus =
        server.status === 'suspended' ||
        server.status === 'provisioning' ||
        server.status === 'provisioning_failed' ||
        server.status === 'terminated'
            ? server.status
            : null;
    const state = (lifecycleStatus ?? stats?.state ?? server.status) as string;
    const health = resolveHealthColor(state);
    const isRunning = state === 'running' || state === 'active';
    const isStopped = state === 'stopped' || state === 'offline' || !stats;
    const isSuspended = state === 'suspended';
    const isProvisioning = state === 'provisioning' || state === 'provisioning_failed';
    const address = server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;

    const ramLimitMb = server.plan?.ram ?? server.limits?.memory ?? 0;
    const ramUsedMb = stats ? stats.memory_bytes / (1024 * 1024) : 0;
    const ramPct = ramLimitMb > 0 ? Math.min(100, (ramUsedMb / ramLimitMb) * 100) : 0;
    const cpuPct = Math.min(100, stats?.cpu ?? 0);

    const handlePrefetch = useCallback(() => {
        void queryClient.prefetchQuery({
            queryKey: ['servers', server.id],
            queryFn: () => fetchServer(server.id),
            staleTime: 120_000,
        });
    }, [queryClient, server.id]);

    const handleCopy = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (!address) return;
        void copyToClipboard(address).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    const handleSelect = (e: React.MouseEvent) => {
        e.stopPropagation();
        onSelect(server.id);
    };

    return (
        <div
            role="button"
            tabIndex={0}
            onClick={() => navigate(`/servers/${server.id}`)}
            onKeyDown={(e) => {
                if (e.key === 'Enter') navigate(`/servers/${server.id}`);
            }}
            onMouseEnter={handlePrefetch}
            className={clsx(
                'group relative flex items-center gap-3 px-3 sm:px-4 py-3 cursor-pointer outline-none',
                'transition-colors duration-150',
                'hover:bg-[var(--color-surface-hover)]/60 focus-visible:bg-[var(--color-surface-hover)]/80',
                !isFirst && 'border-t border-[var(--color-border)]/40',
                isSelected && 'bg-[var(--color-primary-glow)]/10',
            )}
        >
            <span
                aria-hidden
                className={clsx('absolute left-0 top-0 bottom-0 w-[3px] transition-opacity', isRunning && 'animate-pulse')}
                style={{ background: health.color, opacity: isStopped ? 0.4 : 1 }}
            />

            {isSelectionMode && (
                <button
                    type="button"
                    onClick={handleSelect}
                    aria-label={isSelected ? 'Deselect' : 'Select'}
                    className={clsx(
                        'flex h-5 w-5 items-center justify-center rounded border cursor-pointer flex-shrink-0',
                        isSelected
                            ? 'border-[var(--color-primary)] bg-[var(--color-primary)]'
                            : 'border-[var(--color-border-hover)] bg-transparent',
                    )}
                >
                    {isSelected && (
                        <svg className="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                        </svg>
                    )}
                </button>
            )}

            <div className="flex min-w-0 flex-1 items-center gap-3">
                <div className="flex min-w-0 flex-col gap-0.5 sm:flex-1 sm:max-w-[28%]">
                    <span className="truncate text-sm font-semibold text-[var(--color-text-primary)] group-hover:text-[var(--color-primary)] transition-colors">
                        {server.name}
                    </span>
                    {(cardConfig.show_egg_name || cardConfig.show_plan_name) && (
                        <span className="truncate text-[11px] text-[var(--color-text-muted)]">
                            {cardConfig.show_egg_name && server.egg?.name}
                            {cardConfig.show_egg_name && cardConfig.show_plan_name && server.plan && ' · '}
                            {cardConfig.show_plan_name && server.plan?.name}
                        </span>
                    )}
                </div>

                {cardConfig.show_ip_port && address && (
                    <button
                        type="button"
                        onClick={handleCopy}
                        className="hidden md:inline-flex items-center gap-1.5 rounded-[var(--radius-full)] border border-[var(--color-border)]/60 bg-[var(--color-background)]/40 px-2.5 py-1 font-mono text-[11px] text-[var(--color-text-secondary)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] transition-colors cursor-pointer"
                    >
                        <span className="h-1.5 w-1.5 rounded-full" style={{ background: health.color }} aria-hidden />
                        {copied ? t('servers.list.copied') : address}
                    </button>
                )}

                {cardConfig.show_stats_bars && stats && !isSuspended && !isProvisioning && (
                    <div className="hidden lg:flex items-center gap-3 ml-auto">
                        <StatBar label="CPU" pct={cpuPct} value={formatCpu(stats.cpu)} color={health.color} />
                        <StatBar label="RAM" pct={ramPct} value={formatBytes(stats.memory_bytes)} color={health.color} />
                        {cardConfig.show_uptime && (
                            <span className="hidden xl:inline-block w-14 text-right font-mono text-[11px] tabular-nums text-[var(--color-text-muted)]">
                                {stats.uptime > 0 ? formatUptime(stats.uptime) : '—'}
                            </span>
                        )}
                    </div>
                )}

                {(isSuspended || isProvisioning) && (
                    <span
                        className="ml-auto rounded-[var(--radius-full)] px-2.5 py-1 text-[11px] font-medium"
                        style={{
                            background: `${health.color}1A`,
                            color: health.color,
                        }}
                    >
                        {t(`servers.status.${state}`, state)}
                    </span>
                )}

                {cardConfig.show_quick_actions && !isSuspended && !isProvisioning && (
                    <div
                        className="opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity"
                        onClick={(e) => e.stopPropagation()}
                        onKeyDown={(e) => e.stopPropagation()}
                        role="presentation"
                    >
                        <ServerCardPowerButtons
                            serverId={server.id}
                            isRunning={isRunning}
                            isStopped={isStopped}
                            isPowerPending={isPowerPending}
                            onPower={onPower}
                            layout="icon-only"
                        />
                    </div>
                )}
            </div>
        </div>
    );
}

function StatBar({ label, pct, value, color }: { label: string; pct: number; value: string; color: string }) {
    return (
        <div className="flex items-center gap-2 w-32">
            <span className="text-[10px] uppercase tracking-wider text-[var(--color-text-muted)]">{label}</span>
            <div className="flex-1 h-1 rounded-full overflow-hidden bg-[var(--color-border)]/40">
                <div
                    className="h-full transition-all duration-500"
                    style={{ width: `${pct}%`, background: color, opacity: 0.85 }}
                />
            </div>
            <span className="w-14 text-right font-mono text-[11px] tabular-nums text-[var(--color-text-secondary)]">
                {value}
            </span>
        </div>
    );
}

const CommandBarRow = memo(CommandBarRowImpl);

export const CommandBarLayout = memo(CommandBarLayoutImpl);
