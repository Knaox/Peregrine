import { useTranslation } from 'react-i18next';
import clsx from 'clsx';
import { usePowerAction } from '@/hooks/usePowerAction';
import type { PowerSignal } from '@/types/PowerSignal';
import type { ServerPowerControlsProps } from '@/components/server/ServerPowerControls.props';

function PowerButton({ label, onClick, disabled, variant }: {
    label: string;
    onClick: () => void;
    disabled?: boolean;
    variant: 'start' | 'restart' | 'stop' | 'kill';
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={clsx(
                'inline-flex items-center gap-1.5 px-3.5 py-1.5 text-sm rounded-lg',
                'transition-all duration-150',
                'disabled:opacity-40 disabled:cursor-not-allowed',
                variant === 'start' && 'bg-[var(--color-success)] text-white font-semibold hover:shadow-[0_0_16px_var(--color-success-glow)] hover:scale-[1.03]',
                variant === 'restart' && 'border border-white/20 text-white hover:bg-white/10 hover:border-white/30 hover:scale-[1.03]',
                variant === 'stop' && 'bg-[var(--color-danger)] text-white font-semibold hover:shadow-[0_0_16px_var(--color-danger-glow)] hover:scale-[1.03]',
                variant === 'kill' && 'text-[var(--color-danger)] text-[13px] hover:bg-[var(--color-danger)]/10 hover:scale-[1.03]',
            )}
        >
            {disabled && (
                <svg className="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
            )}
            {label}
        </button>
    );
}

export function ServerPowerControls({ serverId, state }: ServerPowerControlsProps) {
    const { t } = useTranslation();
    const { sendPower, isPending } = usePowerAction();

    const isStopped = state === 'offline' || state === 'stopped';
    const isRunning = state === 'running';

    function handlePower(signal: PowerSignal) {
        if (signal === 'kill') {
            const confirmed = window.confirm(t('servers.power.confirm_kill'));
            if (!confirmed) return;
        }
        sendPower({ serverId, signal });
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            {isStopped && (
                <PowerButton label={t('servers.actions.start')} variant="start" disabled={isPending} onClick={() => handlePower('start')} />
            )}
            {isRunning && (
                <>
                    <PowerButton label={t('servers.actions.restart')} variant="restart" disabled={isPending} onClick={() => handlePower('restart')} />
                    <PowerButton label={t('servers.actions.stop')} variant="stop" disabled={isPending} onClick={() => handlePower('stop')} />
                </>
            )}
            <PowerButton label={t('servers.actions.kill')} variant="kill" disabled={isPending} onClick={() => handlePower('kill')} />
        </div>
    );
}
