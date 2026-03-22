import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { IconButton } from '@/components/ui/IconButton';
import { formatBytes, formatCpu } from '@/utils/format';
import type { ServerCardProps } from '@/components/server/ServerCard.props';

const statusBorderColor: Record<string, string> = {
    running: 'border-l-green-500',
    active: 'border-l-green-500',
    stopped: 'border-l-gray-500',
    offline: 'border-l-red-500',
    suspended: 'border-l-amber-500',
    terminated: 'border-l-red-500',
};

const PlayIcon = (
    <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
);
const StopIcon = (
    <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h12v12H6z" /></svg>
);
const RestartIcon = (
    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h5M20 20v-5h-5M20.49 9A9 9 0 0 0 5.64 5.64L4 7m16 10l-1.64 1.36A9 9 0 0 1 3.51 15" />
    </svg>
);
const CopyIcon = (
    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <rect x="9" y="9" width="13" height="13" rx="2" ry="2" /><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
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

    const state = stats?.state ?? server.status;
    const borderClass = statusBorderColor[state] ?? 'border-l-gray-500';
    const isRunning = state === 'running' || state === 'active';
    const isStopped = state === 'stopped' || state === 'offline' || !stats;
    const address = server.allocation ? `${server.allocation.ip}:${server.allocation.port}` : null;

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
        <div
            role="button"
            tabIndex={0}
            onClick={() => navigate(`/servers/${server.id}`)}
            onKeyDown={(e) => { if (e.key === 'Enter') navigate(`/servers/${server.id}`); }}
            className={clsx(
                'group relative flex h-28 cursor-pointer overflow-hidden rounded-[var(--radius)] border-l-4 border border-[var(--color-border)] bg-[var(--color-surface)] transition-all hover:shadow-lg hover:shadow-black/30',
                borderClass,
                isDragging && 'opacity-50',
                isSelected && 'ring-2 ring-[var(--color-primary)]',
            )}
        >
            {/* Egg banner — takes ~40% width as background */}
            <div className="relative w-2/5 flex-shrink-0 overflow-hidden">
                {server.egg?.banner_image ? (
                    <img
                        src={server.egg.banner_image}
                        alt={server.egg.name}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-[var(--color-surface-hover)] to-[var(--color-background)]">
                        <span className="text-sm font-bold uppercase tracking-wider text-[var(--color-text-muted)]">
                            {server.egg?.name ?? '?'}
                        </span>
                    </div>
                )}
                {/* Fade edge to blend into info section */}
                <div className="absolute inset-y-0 right-0 w-16 bg-gradient-to-r from-transparent to-[var(--color-surface)]" />
            </div>

            {/* Info section — name + address + egg */}
            <div className="flex min-w-0 flex-1 flex-col justify-center py-3 pr-2">
                <span className="truncate text-base font-bold text-[var(--color-text-primary)] transition-colors group-hover:text-[var(--color-primary)]">
                    {server.name}
                </span>
                {address && (
                    <button
                        type="button"
                        onClick={handleCopy}
                        className="mt-1 inline-flex w-fit items-center gap-1.5 rounded bg-[var(--color-background)]/50 px-2 py-0.5 text-xs text-[var(--color-text-secondary)] transition-colors hover:text-[var(--color-text-primary)]"
                    >
                        {CopyIcon}
                        {copied ? t('servers.list.copied') : address}
                    </button>
                )}
            </div>

            {/* Right section — power buttons + stats */}
            {/* eslint-disable-next-line jsx-a11y/click-events-have-key-events, jsx-a11y/no-static-element-interactions */}
            <div className="flex flex-shrink-0 items-center gap-5 px-4" onClick={(e) => e.stopPropagation()}>
                {/* Power buttons */}
                <div className="flex items-center gap-1.5">
                    {isStopped && (
                        <IconButton icon={PlayIcon} size="sm" title={t('servers.actions.start')} disabled={isPowerPending} onClick={() => onPower(server.id, 'start')} />
                    )}
                    {isRunning && (
                        <>
                            <IconButton icon={StopIcon} size="sm" title={t('servers.actions.stop')} disabled={isPowerPending} onClick={() => onPower(server.id, 'stop')} />
                            <IconButton icon={RestartIcon} size="sm" title={t('servers.actions.restart')} disabled={isPowerPending} onClick={() => onPower(server.id, 'restart')} />
                        </>
                    )}
                </div>

                {/* Compact stats */}
                {stats && (
                    <div className="hidden flex-col items-end gap-0.5 text-xs sm:flex">
                        <span className="text-[var(--color-text-secondary)]">
                            <span className="text-[var(--color-text-muted)]">{t('servers.resources.cpu')}</span> {formatCpu(stats.cpu)}
                        </span>
                        <span className="text-[var(--color-text-secondary)]">
                            <span className="text-[var(--color-text-muted)]">{t('servers.resources.memory')}</span> {formatBytes(stats.memory_bytes)}
                        </span>
                        <span className="text-[var(--color-text-secondary)]">
                            <span className="text-[var(--color-text-muted)]">{t('servers.resources.disk')}</span> {formatBytes(stats.disk_bytes)}
                        </span>
                    </div>
                )}
            </div>

            {/* Selection checkbox */}
            {isSelectable && (
                <button
                    type="button"
                    onClick={handleSelect}
                    className="absolute left-6 top-2 flex h-5 w-5 items-center justify-center rounded border border-[var(--color-border)] bg-[var(--color-surface)]/80 backdrop-blur-sm transition-colors"
                >
                    {isSelected && (
                        <svg className="h-3.5 w-3.5 text-[var(--color-primary)]" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z" />
                        </svg>
                    )}
                </button>
            )}
        </div>
    );
}
