import { Check, Save } from 'lucide-react';
import { useT } from '../lib/i18n';
import { Button } from '../ui/Button';

/**
 * Sticky bottom-centre save bar (glass), shown only while there are unsaved
 * changes (or for the 2s success flash). States: idle -> loading -> success.
 */
export function FloatingSaveBar({ saving, saved, onSave }: { saving: boolean; saved: boolean; onSave: () => void }) {
    const { t } = useT();

    return (
        <div className="ec-save-bar">
            <span className="ec-save-bar-text">{saved ? t('save.saved') : t('save.unsaved')}</span>
            <Button onClick={onSave} loading={saving} disabled={saved}>
                {saved ? <Check size={15} /> : <Save size={15} />}
                {saved ? t('save.saved') : t('save.save')}
            </Button>
        </div>
    );
}
