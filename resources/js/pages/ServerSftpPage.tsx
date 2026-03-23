import { useParams } from 'react-router-dom';
import { m } from 'motion/react';
import { useServer } from '@/hooks/useServer';
import { SftpCredentials } from '@/components/server/SftpCredentials';
import { Spinner } from '@/components/ui/Spinner';

export function ServerSftpPage() {
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: server, isLoading } = useServer(serverId);

    if (isLoading || !server) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    return (
        <m.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.35, ease: 'easeOut' }}
        >
            <SftpCredentials server={server} />
        </m.div>
    );
}
