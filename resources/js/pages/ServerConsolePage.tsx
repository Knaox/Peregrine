import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useWingsWebSocket } from '@/hooks/useWingsWebSocket';
import { useCommandHistory } from '@/hooks/useCommandHistory';
import { StatusDot } from '@/components/ui/StatusDot';
import { Button } from '@/components/ui/Button';
import { ServerPowerControls } from '@/components/server/ServerPowerControls';
import { ConsoleOutput } from '@/components/console/ConsoleOutput';
import { ConsoleInput } from '@/components/console/ConsoleInput';

export function ServerConsolePage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);

    const { messages, serverState, isConnected, sendCommand, clearMessages } = useWingsWebSocket(serverId, {
        console: true,
        stats: true,
    });

    const { addCommand, navigateUp, navigateDown } = useCommandHistory(serverId);

    const handleSend = (command: string) => {
        sendCommand(command);
        addCommand(command);
    };

    return (
        <m.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.35, ease: 'easeOut' }}
            className="flex h-[calc(100vh-6rem)] flex-col gap-3"
        >
            {/* Header: status + power controls + clear */}
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <div className="inline-flex items-center gap-2 rounded-[var(--radius-full)] backdrop-blur-md bg-[var(--color-glass)] border border-[var(--color-glass-border)] px-3 py-1.5">
                        <StatusDot status={isConnected ? 'running' : 'offline'} size="sm" />
                        <span className="text-xs font-medium text-[var(--color-text-secondary)]">
                            {isConnected ? t('servers.console.connected') : t('servers.console.disconnected')}
                        </span>
                    </div>
                    <ServerPowerControls
                        serverId={serverId}
                        state={serverState as 'running' | 'stopped' | 'offline' | 'starting'}
                    />
                </div>
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={clearMessages}
                    className="backdrop-blur-md bg-[var(--color-glass)] border border-[var(--color-glass-border)]"
                >
                    {t('servers.console.clear')}
                </Button>
            </div>

            <ConsoleOutput messages={messages} />

            <ConsoleInput
                onSend={handleSend}
                onHistoryUp={navigateUp}
                onHistoryDown={navigateDown}
                disabled={!isConnected}
            />
        </m.div>
    );
}
