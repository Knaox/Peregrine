import { AlertTriangle, Power } from 'lucide-react';
import { useT } from '../lib/i18n';
import type { ServerState } from '../types';
import { Button } from '../ui/Button';

/**
 * Non-blocking notice shown above the editor while the server is running. The
 * configuration stays fully readable (same collapse layout as offline) but
 * read-only: editing a value is intercepted with a "stop the server" message.
 * Offers a one-click stop.
 */
export function RunningBanner({ state, onStop, stopping }: { state: ServerState; onStop: () => void; stopping: boolean }) {
    const { t } = useT();

    return (
        <div className="ec-banner" role="status">
            <span className="ec-banner-icon">
                <AlertTriangle size={18} />
            </span>
            <div className="ec-banner-body">
                <div className="ec-banner-title">{t('overlay.running_title')}</div>
                <div className="ec-field-desc">{t('overlay.running_desc')}</div>
            </div>
            <Button onClick={onStop} loading={stopping || state === 'stopping'}>
                <Power size={15} /> {t('overlay.stop_button')}
            </Button>
        </div>
    );
}
