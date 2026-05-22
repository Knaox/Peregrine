import { useT } from '../lib/i18n';
import { Spinner } from '../ui/surfaces';
import { ToastProvider } from '../ui/Toast';
import { ConfigEditor } from './ConfigEditor';
import { RunningOverlay } from './RunningOverlay';
import { usePower, useServerConfig, useServerStatus } from './hooks/useServerConfig';

/**
 * Player-facing "Game configuration" section, registered as a server overview
 * home section. Renders nothing when the server has no template for its egg or
 * the caller lacks access (the API 404/403s). While the server is running, the
 * editor is locked behind a stop-the-server overlay; status is polled every 5s.
 */
function ConfigSectionInner({ serverId }: { serverId: number }) {
    const { t } = useT();
    const config = useServerConfig(serverId);
    const hasTemplates = (config.data?.templates.length ?? 0) > 0;
    const status = useServerStatus(serverId, hasTemplates);
    const power = usePower(serverId);

    if (config.isLoading) {
        return (
            <div className="ec-card ec-row ec-muted">
                <Spinner /> {t('common.loading')}
            </div>
        );
    }

    if (config.isError || !config.data || !hasTemplates) {
        return null;
    }

    const state = status.data?.state ?? 'offline';
    const running = status.isSuccess && state !== 'offline';

    return (
        <div className="ec-relative">
            <ConfigEditor key={serverId} serverId={serverId} templates={config.data.templates} disabled={running} />
            {running && <RunningOverlay state={state} stopping={power.isPending} onStop={() => power.mutate('stop')} />}
        </div>
    );
}

export function ConfigSection({ serverId }: { serverId: number }) {
    return (
        <ToastProvider>
            <div className="ec-root">
                <ConfigSectionInner serverId={serverId} />
            </div>
        </ToastProvider>
    );
}
