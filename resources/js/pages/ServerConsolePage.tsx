import { useMemo } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useWingsWebSocket } from '@/hooks/useWingsWebSocket';
import { useCommandHistory } from '@/hooks/useCommandHistory';
import { useServer } from '@/hooks/useServer';
import { useServerPermissions } from '@/hooks/useServerPermissions';
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

    const { data: server } = useServer(serverId);
    const perms = useServerPermissions(server);
    const canConsole = perms.has('control.console');
    const canStart = perms.has('control.start');
    const canStop = perms.has('control.stop');
    const canRestart = perms.has('control.restart');

    const { messages, serverState, isConnected, sendCommand, clearMessages } = useWingsWebSocket(serverId, {
        console: true, stats: true,
    });

    const { addCommand, navigateUp, navigateDown } = useCommandHistory(serverId);

    const handleSend = (command: string) => {
        if (!canConsole) return;
        sendCommand(command);
        addCommand(command);
    };

    const isStopped = serverState === 'offline' || serverState === 'stopped';
    const isStarting = serverState === 'starting';
    const isStopping = serverState === 'stopping';

    const stateLabel = useMemo(() => {
        if (serverState === 'running') return t('servers.console.status_running');
        if (isStarting) return t('servers.console.status_starting');
        if (isStopping) return t('servers.console.status_stopping');
        return t('servers.console.status_stopped');
    }, [serverState, isStarting, isStopping, t]);

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
            className="flex flex-1 flex-col gap-2 sm:gap-3 overflow-hidden min-h-0"
        >
            {/* Header bar */}
            <div className="flex flex-col gap-2 flex-shrink-0">
                {/* Row 1: status + connection + clear */}
                <div className="flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2 flex-wrap min-w-0">
                        {/* Server state pill */}
                        <div className="inline-flex items-center gap-1.5 rounded-[var(--radius-full)] px-2.5 py-1 sm:px-3 sm:py-1.5 glass-card-enhanced"
                            style={{ borderColor: STATE_COLORS[serverState] ?? 'var(--color-border)' }}>
                            <StatusDot status={serverState === 'running' ? 'running' : serverState === 'starting' || serverState === 'stopping' ? 'starting' : 'offline'} size="sm" />
                            <span className="text-xs font-semibold" style={{ color: STATE_COLORS[serverState] ?? 'var(--color-text-muted)' }}>
                                {stateLabel}
                            </span>
                        </div>

                        {/* Connection badge */}
                        <span className="inline-flex items-center gap-1.5 text-[10px] font-mono"
                            style={{ color: isConnected ? 'var(--color-success)' : 'var(--color-danger)' }}>
                            <span className="h-1.5 w-1.5 rounded-full" style={{ background: isConnected ? 'var(--color-success)' : 'var(--color-danger)' }} />
                            {isConnected ? t('servers.console.connected') : t('servers.console.disconnected')}
                        </span>
                    </div>

                    <Button variant="ghost" size="sm" onClick={clearMessages}
                        className="glass-card-enhanced flex-shrink-0">
                        <svg className="h-4 w-4 sm:mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                            <path strokeLinecap="round" strokeLinejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        <span className="hidden sm:inline">{t('servers.console.clear')}</span>
                    </Button>
                </div>

                {/* Row 2: power controls */}
                <div className="flex items-center">
                    <ServerPowerControls
                        serverId={serverId}
                        state={serverState as 'running' | 'stopped' | 'offline' | 'starting'}
                        canStart={canStart} canStop={canStop} canRestart={canRestart}
                    />
                </div>
            </div>

            {/* Terminal */}
            <ConsoleOutput messages={enrichedMessages} />

            {/* Command input */}
            {canConsole && (
                <ConsoleInput
                    onSend={handleSend}
                    onHistoryUp={navigateUp}
                    onHistoryDown={navigateDown}
                    disabled={!isConnected}
                />
            )}
        </m.div>
    );
}
