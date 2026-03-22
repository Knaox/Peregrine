import { useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useConsoleWebSocket } from '@/hooks/useConsoleWebSocket';
import { useCommandHistory } from '@/hooks/useCommandHistory';
import { ConsoleOutput } from '@/components/console/ConsoleOutput';
import { ConsoleInput } from '@/components/console/ConsoleInput';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';

export function ServerConsolePage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);

    const {
        messages,
        isConnected,
        sendWsCommand,
        clearMessages,
    } = useConsoleWebSocket(serverId);

    const { addCommand, navigateUp, navigateDown } = useCommandHistory(serverId);

    const handleSend = useCallback(
        (command: string) => {
            sendWsCommand(command);
            addCommand(command);
        },
        [sendWsCommand, addCommand],
    );

    return (
        <div className="flex h-full flex-col gap-4">
            {/* Header */}
            <div className="flex items-center justify-between">
                <Badge color={isConnected ? 'green' : 'red'}>
                    {isConnected
                        ? t('servers.console.connected')
                        : t('servers.console.disconnected')}
                </Badge>
                <Button variant="ghost" size="sm" onClick={clearMessages}>
                    {t('servers.console.clear')}
                </Button>
            </div>

            {/* Console output */}
            <ConsoleOutput messages={messages} />

            {/* Console input */}
            <ConsoleInput
                onSend={handleSend}
                onHistoryUp={navigateUp}
                onHistoryDown={navigateDown}
                disabled={!isConnected}
            />
        </div>
    );
}
