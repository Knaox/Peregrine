import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { usePowerAction } from '@/hooks/usePowerAction';
import type { PowerSignal } from '@/types/PowerSignal';
import type { ServerPowerControlsProps } from '@/components/server/ServerPowerControls.props';

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
                <Button
                    variant="primary"
                    size="sm"
                    isLoading={isPending}
                    onClick={() => handlePower('start')}
                >
                    {t('servers.actions.start')}
                </Button>
            )}

            {isRunning && (
                <>
                    <Button
                        variant="secondary"
                        size="sm"
                        isLoading={isPending}
                        onClick={() => handlePower('restart')}
                    >
                        {t('servers.actions.restart')}
                    </Button>

                    <Button
                        variant="danger"
                        size="sm"
                        isLoading={isPending}
                        onClick={() => handlePower('stop')}
                    >
                        {t('servers.actions.stop')}
                    </Button>
                </>
            )}

            <Button
                variant="ghost"
                size="sm"
                className="text-red-400 hover:text-red-300"
                isLoading={isPending}
                onClick={() => handlePower('kill')}
            >
                {t('servers.actions.kill')}
            </Button>
        </div>
    );
}
