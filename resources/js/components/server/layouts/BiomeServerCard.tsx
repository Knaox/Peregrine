import { memo, useCallback, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useQueryClient } from '@tanstack/react-query';
import clsx from 'clsx';
import { copyToClipboard } from '@/utils/clipboard';
import { formatUptime } from '@/utils/format';
import { fetchServer } from '@/services/serverApi';
import { useCardConfig } from '@/hooks/useCardConfig';
import { ServerCardPowerButtons } from '@/components/server/ServerCardPowerButtons';
import { resolveHealthColor } from '@/components/server/layouts/serverHealth';
import { BiomeGauge } from '@/components/server/layouts/BiomeGauge';
import { BiomeMetricBar } from '@/components/server/layouts/BiomeMetricBar';
import type { ServerCardProps } from '@/components/server/ServerCard.props';
import { useNamespace } from '@/i18n/useNamespace';

const CHECK = <svg className="h-3.5 w-3.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" /></svg>;
const COPY = (
    <svg className="h-3 w-3 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <rect x="9" y="9" width="13" height="13" rx="2" /><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
    </svg>
);

/**
 * Biome server card — game-art banner on top, server info + live meters on the
 * themed surface below. Conforms to ServerCardProps so it slots straight into
 * ServerGrid (categories, drag-reorder, responsive columns, selection). The
 * title sits on the surface (not over the image) so the banner stays clean and
 * legible in BOTH light and dark themes. All colours are theme tokens.
 */
function BiomeServerCardImpl({
    server, stats, onPower, isPowerPending,
    isSelectable = false, isSelected = false, onSelect, isDragging = false,
}: ServerCardProps) {
    useNamespace(["server-overview"] as const);
    const navigate = useNavigate();
    const { t } = useTranslation();
    const queryClient = useQueryClient();
    const cardConfig = useCardConfig();
    const cardRef = useRef<HTMLDivElement>(null);
    const [copied, setCopied] = useState(false);
    const [spot, setSpot] = useState({ x: 50, y: 0 });

    const lifecycle = (server.status === 'suspended' || server.status === 'provisioning' ||
        server.status === 'provisioning_failed' || server.status === 'terminated') ? server.status : null;
    const state = (lifecycle ?? stats?.state ?? server.status) as string;
    const health = resolveHealthColor(state);
    const isRunning = state === 'running' || state === 'active';
    const isStopped = state === 'stopped' || state === 'offline' || !stats;
    const isInactive = state === 'suspended' || state === 'provisioning' || state === 'provisioning_failed';
    const banner = server.egg?.banner_image ?? null;

    // Stats arrive on a poll a beat after the server list. Until the first
    // payload lands we show skeletons rather than a flash of 0 % / 0 B / —.
    const statsLoading = !isInactive && stats === undefined;
    const ramLimit = server.plan?.ram ?? server.limits?.memory ?? 0;
    const diskLimit = server.plan?.disk ?? server.limits?.disk ?? 0;
    const address = server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;

    const handlePrefetch = useCallback(() => {
        void queryClient.prefetchQuery({ queryKey: ['servers', server.id], queryFn: () => fetchServer(server.id), staleTime: 120_000 });
    }, [queryClient, server.id]);
    const handleSelect = (e: React.MouseEvent) => { e.stopPropagation(); onSelect?.(server.id); };
    const handleCopy = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (!address) return;
        void copyToClipboard(address).then(() => { setCopied(true); setTimeout(() => setCopied(false), 1500); });
    };
    const handleMove = (e: React.MouseEvent) => {
        const r = cardRef.current?.getBoundingClientRect();
        if (r) setSpot({ x: ((e.clientX - r.left) / r.width) * 100, y: ((e.clientY - r.top) / r.height) * 100 });
    };

    return (
        <div
            ref={cardRef}
            role="button" tabIndex={0}
            onClick={() => navigate(`/servers/${server.id}`)}
            onKeyDown={(e) => { if (e.key === 'Enter') navigate(`/servers/${server.id}`); }}
            onMouseEnter={handlePrefetch}
            onMouseMove={handleMove}
            className={clsx(
                'group relative flex flex-col overflow-hidden rounded-[var(--radius-lg)] cursor-pointer outline-none',
                'border border-[var(--color-border)] bg-[var(--color-surface)]',
                'transition-[transform,box-shadow,border-color] duration-300 will-change-transform',
                'hover:-translate-y-1.5 hover:border-[var(--color-primary)]/70',
                'hover:shadow-[0_24px_60px_-18px_var(--color-primary-glow),0_0_0_1px_rgba(var(--color-primary-rgb),0.25)]',
                'focus-visible:ring-2 focus-visible:ring-[var(--color-primary)]',
                isSelected && 'ring-2 ring-[var(--color-primary)]',
                isDragging && 'opacity-50',
            )}
        >
            {/* Banner — clean artwork, no heavy scrim (renders well in light + dark). */}
            <div className="relative h-24 overflow-hidden sm:h-28">
                {banner ? (
                    <img src={banner} alt="" aria-hidden
                        className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110"
                        style={{ opacity: isInactive ? 0.55 : 1 }} />
                ) : (
                    // Subtle brand wash — uses primary (not the grey "stopped"
                    // colour) so an offline card never gets a dark veil in light
                    // mode, and stays light because it mixes into --color-surface.
                    <div className="h-full w-full transition-transform duration-700 group-hover:scale-110"
                        style={{ background: 'linear-gradient(135deg, color-mix(in srgb, var(--color-primary) 16%, var(--color-surface)), var(--color-surface))' }} />
                )}
                {/* Seam fade ONLY on the art-less brand wash — over real egg
                    artwork it reads as a veil, so the image stays clean. */}
                {!banner && (
                    <>
                        <div className="pointer-events-none absolute -right-10 -top-12 h-32 w-32 rounded-full" style={{ background: 'var(--color-primary)', opacity: 0.12, filter: 'blur(44px)' }} />
                        <div className="absolute inset-x-0 bottom-0 h-10" style={{ background: 'linear-gradient(180deg, transparent, var(--color-surface))' }} />
                    </>
                )}

                <div className="absolute left-3 top-3 z-10 flex items-center gap-1.5 rounded-[var(--radius-full)] border border-white/10 bg-black/45 px-2.5 py-1 backdrop-blur-md">
                    <span className={clsx('h-2 w-2 rounded-full', health.isAlive && 'biome-orb-alive')} style={{ background: health.color, boxShadow: `0 0 8px ${health.color}` }} />
                    <span className="text-[10px] font-bold uppercase tracking-wider text-white/90">{t(`server-overview:status.${state}`, health.labelKey)}</span>
                </div>

                {isSelectable && (
                    <button type="button" onClick={handleSelect} aria-label={isSelected ? 'Deselect' : 'Select'}
                        className={clsx('absolute right-3 top-3 z-20 flex h-6 w-6 items-center justify-center rounded border cursor-pointer',
                            isSelected ? 'border-[var(--color-primary)] bg-[var(--color-primary)]' : 'border-white/40 bg-black/45 backdrop-blur-sm')}>
                        {isSelected && CHECK}
                    </button>
                )}
            </div>

            {/* Spotlight follow */}
            <div className="pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                style={{ background: `radial-gradient(420px circle at ${spot.x}% ${spot.y}%, rgba(var(--color-primary-rgb),0.10), transparent 65%)` }} />

            {/* Body */}
            <div className="relative z-10 flex flex-1 flex-col gap-3 px-4 pb-4 pt-2">
                <div className="min-w-0">
                    <h3 className="truncate text-base font-extrabold leading-tight text-[var(--color-text-primary)] transition-colors duration-300 group-hover:text-[var(--color-primary)]">
                        {server.name}
                    </h3>
                    {(cardConfig.show_egg_name || cardConfig.show_plan_name) && (
                        <p className="mt-0.5 truncate text-[11px] font-semibold uppercase tracking-wider text-[var(--color-text-muted)]">
                            {cardConfig.show_egg_name && server.egg?.name}
                            {cardConfig.show_egg_name && cardConfig.show_plan_name && server.plan && ' · '}
                            {cardConfig.show_plan_name && server.plan?.name}
                        </p>
                    )}
                </div>

                {isInactive ? (
                    <div className="flex h-[72px] items-center justify-center rounded-[var(--radius-md)] border border-dashed border-[var(--color-border)] text-xs font-medium text-[var(--color-text-muted)]">
                        {t(`server-overview:status.${state}`, health.labelKey)}
                    </div>
                ) : cardConfig.show_stats_bars ? (
                    <div className="flex items-center gap-4">
                        <BiomeGauge cpu={stats?.cpu} label="CPU" loading={statsLoading} live={isRunning}
                            from="var(--color-primary)" to="var(--color-primary-hover)" />
                        <div className="flex flex-1 flex-col gap-2.5">
                            <BiomeMetricBar label="RAM" bytes={stats?.memory_bytes} limitMb={ramLimit} loading={statsLoading} live={isRunning} />
                            <BiomeMetricBar label="Disk" bytes={stats?.disk_bytes} limitMb={diskLimit} loading={statsLoading} live={isRunning} />
                            {cardConfig.show_uptime && (
                                <div className="flex items-center justify-between text-[10px] font-medium text-[var(--color-text-muted)]">
                                    <span className="uppercase tracking-wider">Uptime</span>
                                    <span className="font-mono tabular-nums">
                                        {statsLoading ? '…' : stats && stats.uptime > 0 ? formatUptime(stats.uptime) : '—'}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                ) : null}

                {/* Reserve a stable footer height so the card doesn't shrink
                    when power buttons momentarily disappear during start/stop. */}
                <div className="mt-auto flex min-h-[2.75rem] items-center gap-2 border-t border-[var(--color-border)]/60 pt-3">
                    {(state === 'starting' || state === 'stopping') && (
                        <span className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wider" style={{ color: health.color }}>
                            <span className="h-1.5 w-1.5 animate-pulse rounded-full" style={{ background: health.color }} />
                            {t(`server-overview:status.${state}`, health.labelKey)}…
                        </span>
                    )}
                    {cardConfig.show_ip_port && address && (
                        /* eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events */
                        <button type="button" onClick={handleCopy}
                            className="inline-flex min-w-0 items-center gap-1.5 rounded-[var(--radius-full)] bg-[var(--surface-overlay-soft)] px-2.5 py-1 font-mono text-[10px] text-[var(--color-text-secondary)] transition-colors hover:text-[var(--color-text-primary)] cursor-pointer">
                            {COPY}<span className="truncate">{copied ? t('server-overview:list.copied') : address}</span>
                        </button>
                    )}
                    {cardConfig.show_quick_actions && !isInactive && (
                        /* eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events */
                        <div className="ml-auto flex-shrink-0" onClick={(e) => e.stopPropagation()}>
                            <ServerCardPowerButtons serverId={server.id} isRunning={isRunning} isStopped={isStopped}
                                isPowerPending={isPowerPending} onPower={onPower} layout="icon-only" />
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

export const BiomeServerCard = memo(BiomeServerCardImpl);
