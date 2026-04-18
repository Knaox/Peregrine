import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import type { PowerSignal } from '@/types/PowerSignal';

interface ServerCardPowerButtonsProps {
    serverId: number;
    isRunning: boolean;
    isStopped: boolean;
    isPowerPending: boolean;
    onPower: (serverId: number, signal: PowerSignal) => void;
}

function PowerBtn({ icon, title, disabled, onClick }: {
    icon: React.ReactNode; title: string; disabled?: boolean; onClick: () => void;
}) {
    return (
        <button
            type="button" title={title} disabled={disabled} onClick={onClick}
            className={clsx(
                'flex h-10 w-10 items-center justify-center rounded-full cursor-pointer',
                'border border-[var(--color-border-hover)] bg-transparent',
                'text-[var(--color-text-secondary)]',
                'transition-all duration-200',
                'hover:border-[var(--color-primary)] hover:text-[var(--color-primary)] hover:shadow-[0_0_16px_var(--color-primary-glow)] hover:scale-110',
                'active:scale-95',
                'disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:border-[var(--color-border-hover)] disabled:hover:text-[var(--color-text-secondary)] disabled:hover:shadow-none disabled:hover:scale-100',
            )}
        >
            {icon}
        </button>
    );
}

const PlayIcon = <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>;
const StopIcon = <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h12v12H6z" /></svg>;
const RestartIcon = (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M4 4v5h5M20 20v-5h-5M20.49 9A9 9 0 0 0 5.64 5.64L4 7m16 10l-1.64 1.36A9 9 0 0 1 3.51 15" />
    </svg>
);

export function ServerCardPowerButtons({
    serverId, isRunning, isStopped, isPowerPending, onPower,
}: ServerCardPowerButtonsProps) {
    const { t } = useTranslation();

    return (
        <div className="flex items-center gap-2">
            {isStopped && (
                <PowerBtn icon={PlayIcon} title={t('servers.actions.start')} disabled={isPowerPending} onClick={() => onPower(serverId, 'start')} />
            )}
            {isRunning && (
                <>
                    <PowerBtn icon={PlayIcon} title={t('servers.actions.start')} disabled={isPowerPending} onClick={() => onPower(serverId, 'start')} />
                    <PowerBtn icon={StopIcon} title={t('servers.actions.stop')} disabled={isPowerPending} onClick={() => onPower(serverId, 'stop')} />
                    <PowerBtn icon={RestartIcon} title={t('servers.actions.restart')} disabled={isPowerPending} onClick={() => onPower(serverId, 'restart')} />
                </>
            )}
        </div>
    );
}
