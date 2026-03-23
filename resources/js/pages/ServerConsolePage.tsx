import { useMemo } from 'react';
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
import type { ConsoleMessage } from '@/types/ConsoleMessage';

const STATE_COLORS: Record<string, string> = {
    running: 'var(--color-success)',
    starting: 'var(--color-warning)',
    stopping: 'var(--color-warning)',
    offline: 'var(--color-text-muted)',
    stopped: 'var(--color-text-muted)',
};

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

    const isStopped = serverState === 'offline' || serverState === 'stopped';
    const isStarting = serverState === 'starting';
    const isStopping = serverState === 'stopping';

    // Build status label
    const stateLabel = useMemo(() => {
        if (serverState === 'running') return t('servers.console.status_running');
        if (isStarting) return t('servers.console.status_starting');
        if (isStopping) return t('servers.console.status_stopping');
        return t('servers.console.status_stopped');
    }, [serverState, isStarting, isStopping, t]);

    // Add system message when server is offline
    const enrichedMessages: ConsoleMessage[] = useMemo(() => {
        if (isStopped && messages.length === 0) {
            return [{ id: -1, text: `[Peregrine] ${t('servers.console.server_stopped_message')}`, timestamp: Date.now() }];
        }
        if (isStarting && messages.length === 0) {
            return [{ id: -2, text: `[Peregrine] ${t('servers.console.server_starting_message')}`, timestamp: Date.now() }];
        }
        if (isStopping) {
            return [...messages, { id: -3, text: `[Peregrine] ${t('servers.console.server_stopping_message')}`, timestamp: Date.now() }];
        }
        return messages;
    }, [messages, isStopped, isStarting, isStopping, t]);

    return (
        <m.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.35, ease: 'easeOut' }}
            className="flex h-[calc(100vh-6rem)] flex-col gap-3"
        >
            {/* Header: server state indicator + power controls + clear */}
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    {/* Server state pill */}
                    <div
                        className="inline-flex items-center gap-2 rounded-[var(--radius-full)] backdrop-blur-md px-3 py-1.5"
                        style={{
                            background: 'var(--color-glass)',
                            border: `1px solid ${STATE_COLORS[serverState] ?? 'var(--color-border)'}`,
                        }}
                    >
                        <StatusDot status={serverState === 'running' ? 'running' : serverState === 'starting' || serverState === 'stopping' ? 'starting' : 'offline'} size="sm" />
                        <span className="text-xs font-semibold" style={{ color: STATE_COLORS[serverState] ?? 'var(--color-text-muted)' }}>
                            {stateLabel}
                        </span>
                    </div>

                    {/* Connection indicator */}
                    <span className="text-[10px] text-[var(--color-text-muted)]">
                        {isConnected ? t('servers.console.connected') : t('servers.console.disconnected')}
                    </span>

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

            <ConsoleOutput messages={enrichedMessages} />

            <ConsoleInput
                onSend={handleSend}
                onHistoryUp={navigateUp}
                onHistoryDown={navigateDown}
                disabled={!isConnected}
            />
        </m.div>
    );
}
