import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useWingsWebSocket } from '@/hooks/useWingsWebSocket';
import { useCommandHistory } from '@/hooks/useCommandHistory';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { ConsoleOutput } from '@/components/console/ConsoleOutput';
import { ConsoleInput } from '@/components/console/ConsoleInput';

export function ServerConsolePage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);

    const { messages, isConnected, sendCommand, clearMessages } = useWingsWebSocket(serverId, {
        console: true,
        stats: true,
    });

    const { addCommand, navigateUp, navigateDown } = useCommandHistory(serverId);

    const handleSend = (command: string) => {
        sendCommand(command);
        addCommand(command);
    };

    return (
        <div className="flex h-[calc(100vh-6rem)] flex-col gap-3">
            <div className="flex items-center justify-between">
                <Badge color={isConnected ? 'green' : 'red'}>
                    {isConnected ? t('servers.console.connected') : t('servers.console.disconnected')}
                </Badge>
                <Button variant="ghost" size="sm" onClick={clearMessages}>
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
        </div>
    );
}
