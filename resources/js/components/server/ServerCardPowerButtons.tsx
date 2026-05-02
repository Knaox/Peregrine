import { memo } from 'react';
import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import type { PowerSignal } from '@/types/PowerSignal';
import type { CardConfig } from '@/hooks/useCardConfig';

interface ServerCardPowerButtonsProps {
    serverId: number;
    isRunning: boolean;
    isStopped: boolean;
    isPowerPending: boolean;
    onPower: (serverId: number, signal: PowerSignal) => void;
    /** Defaults to `'full'` — keeps the original 40px round buttons. */
    layout?: CardConfig['card_quick_actions_layout'];
}

const SIZES: Record<NonNullable<ServerCardPowerButtonsProps['layout']>, string> = {
    full: 'h-10 w-10',
    compact: 'h-8 w-8',
    'icon-only': 'h-7 w-7',
};

const ICON_SIZES: Record<NonNullable<ServerCardPowerButtonsProps['layout']>, string> = {
    full: 'h-5 w-5',
    compact: 'h-4 w-4',
    'icon-only': 'h-4 w-4',
};

function PowerBtn({ icon, title, disabled, onClick, layout }: {
    icon: React.ReactNode; title: string; disabled?: boolean; onClick: () => void;
    layout: NonNullable<ServerCardPowerButtonsProps['layout']>;
}) {
    return (
        <button
            type="button" title={title} disabled={disabled} onClick={onClick}
            aria-label={title}
            className={clsx(
                'scale-on-hover',
                'flex items-center justify-center rounded-full cursor-pointer',
                SIZES[layout],
                layout === 'icon-only'
                    ? 'border-0 bg-transparent text-[var(--color-text-secondary)] hover:text-[var(--color-primary)]'
                    : 'border border-[var(--color-border-hover)] bg-transparent text-[var(--color-text-secondary)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] hover:shadow-[0_0_16px_var(--color-primary-glow)]',
                'transition-all duration-200',
                'disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:shadow-none',
            )}
        >
            {icon}
        </button>
    );
}

const PlayIcon = (size: string) => (
    <svg className={size} fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
);
const StopIcon = (size: string) => (
    <svg className={size} fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h12v12H6z" /></svg>
);
const RestartIcon = (size: string) => (
    <svg className={size} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h5M20 20v-5h-5M20.49 9A9 9 0 0 0 5.64 5.64L4 7m16 10l-1.64 1.36A9 9 0 0 1 3.51 15" />
    </svg>
);

function ServerCardPowerButtonsImpl({
    serverId, isRunning, isStopped, isPowerPending, onPower, layout = 'full',
}: ServerCardPowerButtonsProps) {
    const { t } = useTranslation();
    const iconSize = ICON_SIZES[layout];
    const gap = layout === 'full' ? 'gap-2' : 'gap-1';

    return (
        <div className={clsx('flex items-center', gap)}>
            {isStopped && (
                <PowerBtn
                    icon={PlayIcon(iconSize)}
                    title={t('servers.actions.start')}
                    disabled={isPowerPending}
                    onClick={() => onPower(serverId, 'start')}
                    layout={layout}
                />
            )}
            {isRunning && (
                <>
                    <PowerBtn
                        icon={PlayIcon(iconSize)}
                        title={t('servers.actions.start')}
                        disabled={isPowerPending}
                        onClick={() => onPower(serverId, 'start')}
                        layout={layout}
                    />
                    <PowerBtn
                        icon={StopIcon(iconSize)}
                        title={t('servers.actions.stop')}
                        disabled={isPowerPending}
                        onClick={() => onPower(serverId, 'stop')}
                        layout={layout}
                    />
                    <PowerBtn
                        icon={RestartIcon(iconSize)}
                        title={t('servers.actions.restart')}
                        disabled={isPowerPending}
                        onClick={() => onPower(serverId, 'restart')}
                        layout={layout}
                    />
                </>
            )}
        </div>
    );
}

export const ServerCardPowerButtons = memo(ServerCardPowerButtonsImpl);
