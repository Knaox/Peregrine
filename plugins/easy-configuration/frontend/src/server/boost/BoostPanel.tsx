import { History, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { formatDateTime } from '../../lib/format';
import { useT } from '../../lib/i18n';
import { Button, IconButton } from '../../ui/Button';
import { Badge, Card, EmptyState } from '../../ui/surfaces';
import { useBoostHistory, useCancelBoost, type Boost } from './useBoosts';

export function BoostPanel({ serverId, boosts, onNew }: { serverId: number; boosts: Boost[]; onNew: () => void }) {
    const { t } = useT();
    const cancel = useCancelBoost(serverId);
    const [showHistory, setShowHistory] = useState(false);
    const history = useBoostHistory(serverId, showHistory);

    const onCancel = (id: number): void => {
        if (window.confirm(t('boost.confirm_cancel'))) {
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
                        <Button size="sm" onClick={onNew}>
                            <Plus size={14} /> {t('boost.new')}
                        </Button>
                    </div>
                </div>

                {boosts.length === 0 ? (
                    <EmptyState>{t('boost.none')}</EmptyState>
                ) : (
                    <div className="ec-list">
                        {boosts.map((boost) => (
                            <div key={boost.id} className="ec-server-row" style={{ cursor: 'default' }}>
                                <Badge variant={boost.status === 'active' ? 'accent' : 'info'}>x{boost.multiplier}</Badge>
                                <span className="ec-grow">
                                    <span className="ec-truncate">{t('boost.param_count', { count: boost.parameters.length })}</span>
                                    <span className="ec-field-desc ec-muted">
                                        {formatDateTime(boost.start_at)} {String.fromCharCode(8594)} {formatDateTime(boost.end_at)}
                                    </span>
                                </span>
                                <Badge variant={boost.status === 'active' ? 'success' : 'muted'}>{t(`boost.status.${boost.status}`)}</Badge>
                                <IconButton label={t('common.cancel')} onClick={() => onCancel(boost.id)}>
                                    <X size={14} />
                                </IconButton>
                            </div>
                        ))}
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
