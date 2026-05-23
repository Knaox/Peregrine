import clsx from 'clsx';
import { useT } from '../lib/i18n';
import { Tooltip } from '../ui/surfaces';

interface Props {
    selected: boolean;
    /** Already covered by a pending/active boost — checkbox ticked but locked. */
    locked: boolean;
    divide: boolean;
    onToggle: () => void;
    onToggleDivide: () => void;
}

/**
 * Boost selection control in a field row: a checkbox to include the parameter in
 * a new boost, plus — once ticked and not already locked — a × / ÷ segmented
 * toggle. × multiplies the value, ÷ divides it (deboost: shrinks a value or
 * interval). Labels carry the meaning so it never relies on colour alone.
 */
export function BoostSelectToggle({ selected, locked, divide, onToggle, onToggleDivide }: Props) {
    const { t } = useT();

    return (
        <span className="ec-row" style={{ gap: '0.4rem' }}>
            <Tooltip content={t(locked ? 'boost.already_boosted' : 'boost.boost_this')}>
                <label className="ec-row" style={{ cursor: locked ? 'not-allowed' : 'pointer' }}>
                    <input type="checkbox" checked={selected} disabled={locked} onChange={onToggle} />
                </label>
            </Tooltip>
            {selected && !locked && (
                <span className="ec-seg" role="group" aria-label={t('boost.factor_aria')}>
                    <Tooltip content={t('boost.multiply_tooltip')}>
                        <button
                            type="button"
                            className={clsx('ec-seg-btn', !divide && 'ec-seg-on')}
                            aria-pressed={!divide}
                            onClick={() => {
                                if (divide) {
                                    onToggleDivide();
                                }
                            }}
                        >
                            {t('boost.factor_multiply')}
                        </button>
                    </Tooltip>
                    <Tooltip content={t('boost.divide_tooltip')}>
                        <button
                            type="button"
                            className={clsx('ec-seg-btn', divide && 'ec-seg-on')}
                            aria-pressed={divide}
                            onClick={() => {
                                if (!divide) {
                                    onToggleDivide();
                                }
                            }}
                        >
                            {t('boost.factor_divide')}
                        </button>
                    </Tooltip>
                </span>
            )}
        </span>
    );
}
