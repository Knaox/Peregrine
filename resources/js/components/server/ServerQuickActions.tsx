import { useTranslation } from 'react-i18next';
import { IconButton } from '@/components/ui/IconButton';
import type { ServerQuickActionsProps } from '@/components/server/ServerQuickActions.props';

const PlayIcon = (
    <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
        <path d="M8 5v14l11-7z" />
    </svg>
);

const StopIcon = (
    <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
        <path d="M6 6h12v12H6z" />
    </svg>
);

const RestartIcon = (
    <svg className="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path
            strokeLinecap="round"
            strokeLinejoin="round"
            d="M4 4v5h5M20 20v-5h-5M20.49 9A9 9 0 0 0 5.64 5.64L4 7m16 10l-1.64 1.36A9 9 0 0 1 3.51 15"
        />
    </svg>
);

export function ServerQuickActions({ serverId, state, onPower, isPending }: ServerQuickActionsProps) {
    const { t } = useTranslation();

    const isRunning = state === 'running' || state === 'active';
    const isStopped = state === 'stopped' || state === 'offline';

    return (
        <div className="flex items-center gap-1">
            {isStopped && (
                <IconButton
                    icon={PlayIcon}
                    size="sm"
                    title={t('servers.actions.start')}
                    disabled={isPending}
                    isLoading={isPending}
                    onClick={() => onPower(serverId, 'start')}
                />
            )}
            {isRunning && (
                <>
                    <IconButton
                        icon={StopIcon}
                        size="sm"
                        title={t('servers.actions.stop')}
                        disabled={isPending}
                        isLoading={isPending}
                        onClick={() => onPower(serverId, 'stop')}
                    />
                    <IconButton
                        icon={RestartIcon}
                        size="sm"
                        title={t('servers.actions.restart')}
                        disabled={isPending}
                        isLoading={isPending}
                        onClick={() => onPower(serverId, 'restart')}
                    />
                </>
            )}
        </div>
    );
}
