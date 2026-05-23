import { Zap } from 'lucide-react';
import { formatDateTime } from '../../lib/format';
import { useT } from '../../lib/i18n';
import type { ParamBoost } from '../../types';
import { Badge, Tooltip } from '../../ui/surfaces';

/** Badge shown on a parameter that has a planned or active boost. */
export function BoostBadge({ boost }: { boost: ParamBoost }) {
    const { t } = useT();
    // ÷ when the parameter is deboosted (divided), × otherwise.
    const factor = boost.invert ? '÷' : '×';

    if (boost.status === 'active') {
        return (
            <Tooltip content={t('boost.active_tip', { mult: boost.multiplier, end: formatDateTime(boost.end_at), value: boost.effective_value })}>
                <span>
                    <Badge variant="accent">
                        <Zap size={11} /> {factor}{boost.multiplier} {String.fromCharCode(8594)} {boost.effective_value}
                    </Badge>
                </span>
            </Tooltip>
        );
    }

    return (
        <Tooltip content={t('boost.planned_tip', { mult: boost.multiplier, start: formatDateTime(boost.start_at) })}>
            <span>
                <Badge variant="info">
                    <Zap size={11} /> {factor}{boost.multiplier}
                </Badge>
            </span>
        </Tooltip>
    );
}
