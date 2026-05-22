import { AlertTriangle, Power } from 'lucide-react';
import { useT } from '../lib/i18n';
import type { ServerState } from '../types';
import { Button } from '../ui/Button';

/**
 * Greys out the whole section while the server is running and offers a one-click
 * stop. The parent polls status every 5s and lifts this overlay once offline.
 */
export function RunningOverlay({ state, onStop, stopping }: { state: ServerState; onStop: () => void; stopping: boolean }) {
    const { t } = useT();

    return (
        <div className="ec-overlay">
            <div className="ec-overlay-card">
                <span className="ec-icon-box">
                    <AlertTriangle size={20} />
                </span>
                <p className="ec-title">{t('overlay.running_title')}</p>
                <p className="ec-subtitle">{t('overlay.running_desc')}</p>
                <Button onClick={onStop} loading={stopping || state === 'stopping'}>
                    <Power size={15} /> {t('overlay.stop_button')}
                </Button>
            </div>
        </div>
    );
}
