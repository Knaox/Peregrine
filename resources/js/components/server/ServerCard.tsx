import { useCallback, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import { StatusDot } from '@/components/ui/StatusDot';
import { formatBytes, formatCpu } from '@/utils/format';
import { useCardConfig } from '@/hooks/useCardConfig';
import { useCountUp } from '@/hooks/useCountUp';
import type { ServerCardProps } from '@/components/server/ServerCard.props';

/* Outlined circle power button */
function PowerBtn({ icon, title, disabled, onClick }: {
    icon: React.ReactNode;
    title: string;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            title={title}
            disabled={disabled}
            onClick={onClick}
            className={clsx(
                'flex h-10 w-10 items-center justify-center rounded-full',
                'border border-[var(--color-border-hover)] bg-transparent',
                'text-[var(--color-text-secondary)]',
                'transition-all duration-[var(--transition-base)]',
                'hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] hover:shadow-[var(--shadow-glow)]',
                'disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:border-[var(--color-border-hover)] disabled:hover:text-[var(--color-text-secondary)] disabled:hover:shadow-none',
            )}
        >
            {icon}
        </button>
    );
}

/* Animated stat with count-up + icon */
function AnimStat({ icon, value, formatter }: {
    icon: React.ReactNode;
    value: number;
    formatter: (v: number) => string;
}) {
    const animated = useCountUp(value);
    return (
        <span className="flex items-center gap-1.5 text-[var(--color-text-secondary)]">
            {icon} {formatter(animated)}
        </span>
    );
}

/* Stat icons */
const CpuIcon = (
    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
    </svg>
);
const RamIcon = (
    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
    </svg>
);
const DiskIcon = (
    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M4 7v10c0 1.1.9 2 2 2h12a2 2 0 002-2V7a2 2 0 00-2-2H6a2 2 0 00-2 2zm16 7H4m13 2h.01" />
    </svg>
);
const PlayIcon = (
    <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
);
const StopIcon = (
    <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h12v12H6z" /></svg>
);
const RestartIcon = (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h5M20 20v-5h-5M20.49 9A9 9 0 0 0 5.64 5.64L4 7m16 10l-1.64 1.36A9 9 0 0 1 3.51 15" />
    </svg>
);

export function ServerCard({
    server,
    stats,
    onPower,
    isPowerPending,
    isSelectable = false,
    isSelected = false,
    onSelect,
    isDragging = false,
}: ServerCardProps) {
    const navigate = useNavigate();
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);
    const cardConfig = useCardConfig();
    const cardRef = useRef<HTMLDivElement>(null);
    const [spotlightPos, setSpotlightPos] = useState({ x: 0, y: 0 });

    const state = (stats?.state ?? server.status) as
        'running' | 'active' | 'stopped' | 'offline' | 'suspended' | 'terminated' | 'starting';
    const isRunning = state === 'running' || state === 'active';
    const isStopped = state === 'stopped' || state === 'offline' || !stats;
    const address = server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;

    const handleMouseMove = useCallback((e: React.MouseEvent) => {
        if (!cardRef.current) return;
        const rect = cardRef.current.getBoundingClientRect();
        setSpotlightPos({ x: e.clientX - rect.left, y: e.clientY - rect.top });
    }, []);

    const handleCopy = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (!address) return;
        void navigator.clipboard.writeText(address).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    const handleSelect = (e: React.MouseEvent) => {
        e.stopPropagation();
        onSelect?.(server.id);
    };

    return (
        <m.div
            ref={cardRef}
            layout
            role="button"
            tabIndex={0}
            onClick={() => navigate(`/servers/${server.id}`)}
            onKeyDown={(e) => { if (e.key === 'Enter') navigate(`/servers/${server.id}`); }}
            onMouseMove={handleMouseMove}
            className={clsx(
                'group relative flex h-36 cursor-pointer overflow-hidden border-glow',
                'bg-[var(--color-surface)] border border-[var(--color-border)] rounded-[var(--radius-lg)]',
                'transition-all duration-300',
                'hover:border-[var(--color-border-hover)] hover:shadow-[var(--shadow-glow)] hover:scale-[1.003]',
                isDragging && 'opacity-50 scale-[0.98]',
                isSelected && 'ring-2 ring-[var(--color-primary-glow)]',
            )}
            style={{ boxShadow: 'var(--glass-highlight)' }}
        >
            {/* Card Spotlight — radial gradient follows cursor */}
            <div
                className="card-spotlight"
                style={{
                    background: `radial-gradient(circle 200px at ${spotlightPos.x}px ${spotlightPos.y}px, var(--color-primary-glow), transparent)`,
                }}
            />

            {/* Egg banner — ~50% width with diagonal clip */}
            {cardConfig.show_egg_icon && (
                <div className="relative w-1/2 flex-shrink-0 overflow-hidden">
                    {server.egg?.banner_image ? (
                        <img
                            src={server.egg.banner_image}
                            alt={server.egg.name}
                            className="banner-clip h-full w-full object-cover"
                        />
                    ) : (
                        <div className="banner-clip flex h-full w-full items-center justify-center bg-gradient-to-br from-[var(--color-surface-hover)] to-[var(--color-background)]">
                            <span className="text-lg font-bold uppercase tracking-widest text-[var(--color-text-muted)]">
                                {server.egg?.name ?? '?'}
                            </span>
                        </div>
                    )}
                </div>
            )}

            {/* Center: name + address + status */}
            <div className="relative z-10 flex min-w-0 flex-1 flex-col justify-center gap-1 py-3 pl-2 pr-2">
                <div className="flex items-center gap-2">
                    <StatusDot status={state} size="sm" />
                    <span className="truncate text-lg font-bold text-[var(--color-text-primary)] transition-colors duration-300 group-hover:text-[var(--color-primary)]">
                        {server.name}
                    </span>
                </div>
                {address && (
                    <button
                        type="button"
                        onClick={handleCopy}
                        className="mt-0.5 inline-flex w-fit items-center gap-1 text-sm font-mono text-[var(--color-text-secondary)] transition-colors duration-200 hover:text-[var(--color-text-primary)]"
                    >
                        <span>{copied ? t('servers.list.copied') : address}</span>
                    </button>
                )}
            </div>

            {/* Right: power buttons + animated stats */}
            {/* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions */}
            <div className="relative z-10 flex flex-shrink-0 items-center gap-6 px-5" onClick={(e) => e.stopPropagation()}>
                {cardConfig.show_quick_actions && (
                    <div className="flex items-center gap-2">
                        {isStopped && (
                            <PowerBtn icon={PlayIcon} title={t('servers.actions.start')} disabled={isPowerPending} onClick={() => onPower(server.id, 'start')} />
                        )}
                        {isRunning && (
                            <>
                                <PowerBtn icon={PlayIcon} title={t('servers.actions.start')} disabled={isPowerPending} onClick={() => onPower(server.id, 'start')} />
                                <PowerBtn icon={StopIcon} title={t('servers.actions.stop')} disabled={isPowerPending} onClick={() => onPower(server.id, 'stop')} />
                                <PowerBtn icon={RestartIcon} title={t('servers.actions.restart')} disabled={isPowerPending} onClick={() => onPower(server.id, 'restart')} />
                            </>
                        )}
                    </div>
                )}

                {cardConfig.show_stats_bars && stats && (
                    <div className="hidden items-center gap-4 text-sm sm:flex">
                        <AnimStat icon={CpuIcon} value={stats.cpu} formatter={formatCpu} />
                        <AnimStat icon={RamIcon} value={stats.memory_bytes} formatter={formatBytes} />
                        <AnimStat icon={DiskIcon} value={stats.disk_bytes} formatter={formatBytes} />
                    </div>
                )}
            </div>

            {/* Selection checkbox */}
            {isSelectable && (
                <button
                    type="button"
                    onClick={handleSelect}
                    className={clsx(
                        'absolute left-6 top-2 z-10 flex h-5 w-5 items-center justify-center rounded border transition-all duration-200',
                        isSelected
                            ? 'border-[var(--color-primary)] bg-[var(--color-primary)] ring-2 ring-[var(--color-primary-glow)]'
                            : 'border-[var(--color-border)] bg-[var(--color-surface)]/80 backdrop-blur-sm',
                    )}
                >
                    {isSelected && (
                        <svg className="h-3.5 w-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                        </svg>
                    )}
                </button>
            )}
        </m.div>
    );
}
