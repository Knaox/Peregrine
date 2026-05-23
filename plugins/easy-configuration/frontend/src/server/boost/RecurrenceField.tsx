import { useT } from '../../lib/i18n';
import { toLocalInput } from '../../lib/format';
import { Button } from '../../ui/Button';
import { Input } from '../../ui/inputs';
import type { Recurrence } from './useBoosts';

interface Props {
    /** null = one-shot boost (no repetition). */
    recurrence: Recurrence | null;
    /** '' = repeat indefinitely; otherwise a datetime-local cut-off. */
    until: string;
    onRecurrenceChange: (recurrence: Recurrence | null) => void;
    onUntilChange: (until: string) => void;
}

const OPTIONS: { value: Recurrence | null; key: string }[] = [
    { value: null, key: 'repeat_once' },
    { value: 'daily', key: 'repeat_daily' },
    { value: 'weekly', key: 'repeat_weekly' },
    { value: 'monthly', key: 'repeat_monthly' },
];

const row = { display: 'flex', gap: '0.35rem', flexWrap: 'wrap' as const };

/**
 * Recurrence picker: a segmented "once / daily / weekly / monthly" control, plus
 * — when recurring — an end condition ("no end" vs "until a date"). Built from
 * the existing Button primitive (no segmented-control primitive exists).
 */
export function RecurrenceField({ recurrence, until, onRecurrenceChange, onUntilChange }: Props) {
    const { t } = useT();
    const indefinite = until === '';

    const enableUntil = (): void => onUntilChange(toLocalInput(new Date(Date.now() + 30 * 86_400_000)));

    return (
        <div className="ec-field-group">
            <label>{t('boost.recurrence')}</label>
            <div style={row}>
                {OPTIONS.map((option) => (
                    <Button
                        key={option.key}
                        size="sm"
                        variant={recurrence === option.value ? 'primary' : 'ghost'}
                        onClick={() => onRecurrenceChange(option.value)}
                    >
                        {t(`boost.${option.key}`)}
                    </Button>
                ))}
            </div>

            {recurrence !== null && (
                <div className="ec-stack" style={{ gap: '0.5rem', marginTop: '0.5rem' }}>
                    <div style={row}>
                        <Button size="sm" variant={indefinite ? 'primary' : 'ghost'} onClick={() => onUntilChange('')}>
                            {t('boost.ends_never')}
                        </Button>
                        <Button size="sm" variant={indefinite ? 'ghost' : 'primary'} onClick={enableUntil}>
                            {t('boost.ends_on')}
                        </Button>
                    </div>
                    {!indefinite && (
                        <Input type="datetime-local" value={until} onChange={(event) => onUntilChange(event.target.value)} />
                    )}
                </div>
            )}
        </div>
    );
}
