import { useParams, Link, Outlet } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useServer } from '@/hooks/useServer';
import { Spinner } from '@/components/ui/Spinner';
import { GlassCard } from '@/components/ui/GlassCard';
import { ServerSidebar } from '@/components/server/ServerSidebar';

export function ServerDetailPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: server, isLoading, isError } = useServer(serverId);

    if (isLoading) {
        return (
            <div className="flex h-screen items-center justify-center">
                <Spinner size="lg" />
            </div>
        );
    }

    if (isError || !server) {
        return (
            <div className="flex h-screen flex-col items-center justify-center gap-4">
                <GlassCard className="px-8 py-6 text-center">
                    <p className="text-[var(--color-text-secondary)]">
                        {t('servers.detail.not_found')}
                    </p>
                    <Link
                        to="/dashboard"
                        className="mt-3 inline-block text-sm text-[var(--color-primary)] transition-colors duration-[var(--transition-fast)] hover:text-[var(--color-primary-hover)]"
                    >
                        {t('servers.detail.back')}
                    </Link>
                </GlassCard>
            </div>
        );
    }

    return (
        <div className="flex h-screen overflow-hidden">
            <ServerSidebar server={server} />
            <m.main
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.3 }}
                className="flex-1 overflow-y-auto"
            >
                <div className="p-6">
                    <Outlet />
                </div>
            </m.main>
        </div>
    );
}
