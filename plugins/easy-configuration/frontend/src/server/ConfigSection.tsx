import { useT } from '../lib/i18n';
import { Spinner } from '../ui/surfaces';
import { ToastProvider } from '../ui/Toast';
import { ConfigEditor } from './ConfigEditor';
import { usePower, useServerConfig } from './hooks/useServerConfig';
import { useServerPowerState } from './hooks/useServerPowerState';

/**
 * Player-facing "Game configuration" section, registered as a server overview
 * home section. Renders nothing when the server has no template for its egg or
 * the caller lacks access (the API 404/403s). While the server is running, the
 * editor is locked behind a stop-the-server overlay; the lock flips live (via
 * the home page's Wings socket) the instant the server starts or stops.
 */
function ConfigSectionInner({ serverId }: { serverId: number }) {
    const { t } = useT();
    const config = useServerConfig(serverId);
    const hasTemplates = (config.data?.templates.length ?? 0) > 0;
    const { state, running } = useServerPowerState(serverId, hasTemplates);
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

    return (
        <ConfigEditor
            key={serverId}
            serverId={serverId}
            templates={config.data.templates}
            permissions={config.data.permissions}
            disabled={running}
            state={state}
            stopping={power.isPending}
            onStop={() => power.mutate('stop')}
        />
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
