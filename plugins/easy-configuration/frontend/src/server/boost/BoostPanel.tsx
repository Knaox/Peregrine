import { History, Pencil, Plus, Repeat, X } from 'lucide-react';
import { useState } from 'react';
import { formatDateTime } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Button, IconButton } from '../../ui/Button';
import { Callout } from '../../ui/Callout';
import { Badge, Card, EmptyState } from '../../ui/surfaces';
import { useBoostHistory, useCancelBoost, type Boost } from './useBoosts';

export function BoostPanel({
    serverId,
    boosts,
    selectedCount,
    onNew,
    onEdit,
}: {
    serverId: number;
    boosts: Boost[];
    selectedCount: number;
    onNew: () => void;
    onEdit: (boost: Boost) => void;
}) {
    const { t } = useT();
    const cancel = useCancelBoost(serverId);
    const [showHistory, setShowHistory] = useState(false);
    const [cancellingIds, setCancellingIds] = useState<Set<number>>(new Set());
    const history = useBoostHistory(serverId, showHistory);

    // Cancelling an ACTIVE boost runs asynchronously (stop → restore → restart),
    // so the row reflects the transition immediately with a "cancelling" state
    // until the refreshed list no longer contains it.
    const onCancel = (id: number): void => {
        if (window.confirm(t('boost.confirm_cancel'))) {
            setCancellingIds((current) => new Set(current).add(id));
            cancel.mutate(id);
        }
    };

    return (
        <Card>
            <div className="ec-stack">
                <div className="ec-between">
                    <h3 className="ec-title">{t('boost.panel_title')}</h3>
                    <div className="ec-row">
                        <Button variant="ghost" size="sm" onClick={() => setShowHistory((v) => !v)}>
                            <History size={14} /> {t('boost.history')}
                        </Button>
                        <Button size="sm" disabled={selectedCount === 0} onClick={onNew}>
                            <Plus size={14} /> {selectedCount > 0 ? t('boost.selected_count', { count: selectedCount }) : t('boost.new')}
                        </Button>
                    </div>
                </div>

                <p className="ec-field-desc ec-muted">{t('boost.select_hint')}</p>

                <Callout variant="warning">{t('boost.auto_restart_warning')}</Callout>

                {boosts.length === 0 ? (
                    <EmptyState>{t('boost.none')}</EmptyState>
                ) : (
                    <div className="ec-list">
                        {boosts.map((boost) => {
                            // Server-driven ('cancelling' status) OR the local optimistic flag set on click.
                            const cancelling = cancellingIds.has(boost.id) || boost.status === 'cancelling';

                            return (
                                <div key={boost.id} className="ec-server-row" style={{ cursor: 'default', opacity: cancelling ? 0.6 : 1 }}>
                                    <Badge variant={boost.status === 'active' ? 'accent' : 'info'}>x{boost.multiplier}</Badge>
                                    <span className="ec-grow">
                                        <span className="ec-truncate">{t('boost.param_count', { count: boost.parameters.length })}</span>
                                        <span className="ec-field-desc ec-muted">
                                            {formatDateTime(boost.start_at)} {String.fromCharCode(8594)} {formatDateTime(boost.end_at)}
                                        </span>
                                    </span>
                                    {boost.recurrence && (
                                        <Badge variant="info">
                                            <Repeat size={11} /> {t(`boost.repeat_${boost.recurrence}`)}
                                        </Badge>
                                    )}
                                    {cancelling ? (
                                        <Badge variant="warning">{t('boost.status.cancelling')}</Badge>
                                    ) : (
                                        <Badge variant={boost.status === 'active' ? 'success' : 'muted'}>{t(`boost.status.${boost.status}`)}</Badge>
                                    )}
                                    {boost.status === 'pending' && !cancelling && (
                                        <IconButton label={t('boost.edit_title')} onClick={() => onEdit(boost)}>
                                            <Pencil size={14} />
                                        </IconButton>
                                    )}
                                    <IconButton label={t('common.cancel')} disabled={cancelling} onClick={() => onCancel(boost.id)}>
                                        <X size={14} />
                                    </IconButton>
                                </div>
                            );
                        })}
                    </div>
                )}

                {showHistory && (
                    <div className="ec-list">
                        <p className="ec-section-label">{t('boost.history')}</p>
                        {(history.data ?? []).length === 0 ? (
                            <EmptyState>{t('boost.no_history')}</EmptyState>
                        ) : (
                            (history.data ?? []).map((row) => (
                                <div key={row.id} className="ec-server-row" style={{ cursor: 'default' }}>
                                    <Badge variant="muted">x{row.multiplier}</Badge>
                                    <span className="ec-grow ec-truncate">{formatDateTime(row.start_at)} {String.fromCharCode(8594)} {formatDateTime(row.end_at)}</span>
                                    <Badge variant="muted">{t(`boost.final.${row.final_status}`)}</Badge>
                                </div>
                            ))
                        )}
                    </div>
                )}
            </div>
        </Card>
    );
}
