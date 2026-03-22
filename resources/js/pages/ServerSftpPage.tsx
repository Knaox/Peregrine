import { useParams } from 'react-router-dom';
import { useServer } from '@/hooks/useServer';
import { useAuthStore } from '@/stores/authStore';
import { SftpCredentials } from '@/components/server/SftpCredentials';
import { Spinner } from '@/components/ui/Spinner';

export function ServerSftpPage() {
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: server, isLoading } = useServer(serverId);
    const { user } = useAuthStore();

    if (isLoading || !server) {
        return (
            <div className="flex justify-center py-12">
                <Spinner size="lg" />
            </div>
        );
    }

    return <SftpCredentials server={server} userEmail={user?.email ?? ''} />;
}
