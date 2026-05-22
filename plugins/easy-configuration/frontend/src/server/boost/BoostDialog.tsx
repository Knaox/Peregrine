import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import { fieldKey } from '../../lib/fieldKey';
import { toLocalInput } from '../../lib/format';
import { useT } from '../../lib/i18n';
import type { ApiError } from '../../shared';
import { Button } from '../../ui/Button';
import { Dialog } from '../../ui/Dialog';
import { Input } from '../../ui/inputs';
import { useToast } from '../../ui/Toast';
import { useCreateBoost } from './useBoosts';

export interface BoostableParam {
    template_id: string;
    file_id: string;
    section: string | null;
    key: string;
    label: string;
    max?: number;
}

interface Props {
    open: boolean;
    onClose: () => void;
    serverId: number;
    params: BoostableParam[];
}

export function BoostDialog({ open, onClose, serverId, params }: Props) {
    const { t } = useT();
    const toast = useToast();
    const create = useCreateBoost(serverId);

    const now = new Date();
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [caps, setCaps] = useState<Record<string, string>>({});
    const [multiplier, setMultiplier] = useState('2');
    const [startAt, setStartAt] = useState(toLocalInput(now));
    const [endAt, setEndAt] = useState(toLocalInput(new Date(now.getTime() + 86_400_000)));

    const identity = (p: BoostableParam): string => fieldKey(p.file_id, p.section, p.key);

    const toggle = (id: string): void => {
        setSelected((current) => {
            const next = new Set(current);
            next.has(id) ? next.delete(id) : next.add(id);

            return next;
        });
    };

    const submit = (): void => {
        const chosen = params.filter((p) => selected.has(identity(p)));
        if (chosen.length === 0 || Number(multiplier) <= 0) {
            toast.error(t('boost.invalid'));

            return;
        }

        create.mutate(
            {
                template_id: chosen[0]?.template_id ?? '',
                multiplier: Number(multiplier),
                start_at: new Date(startAt).toISOString(),
                end_at: new Date(endAt).toISOString(),
                parameters: chosen.map((p) => {
                    const cap = caps[identity(p)];
                    return { file_id: p.file_id, section: p.section, key: p.key, max_cap: cap !== undefined && cap !== '' ? Number(cap) : null };
                }),
            },
            {
                onSuccess: () => {
                    toast.success(t('boost.created'));
                    onClose();
                },
                onError: (error) => {
                    const apiError = error as unknown as ApiError;
                    toast.error(apiError.code === 'boost_overlap' ? t('boost.overlap') : t('boost.create_failed'));
                },
            },
        );
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            closeLabel={t('common.close')}
            title={t('boost.new_title')}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button>
                    <Button loading={create.isPending} onClick={submit}>{t('boost.schedule')}</Button>
                </>
            }
        >
            <div className="ec-dialog-body">
                <div className="ec-cols-2">
                    <div className="ec-field-group">
                        <label>{t('boost.multiplier')}</label>
                        <Input type="number" min={0} step="0.1" value={multiplier} onChange={(e) => setMultiplier(e.target.value)} />
                    </div>
                </div>
                <div className="ec-cols-2">
                    <div className="ec-field-group">
                        <label>{t('boost.start_at')}</label>
                        <Input type="datetime-local" value={startAt} onChange={(e) => setStartAt(e.target.value)} />
                    </div>
                    <div className="ec-field-group">
                        <label>{t('boost.end_at')}</label>
                        <Input type="datetime-local" value={endAt} onChange={(e) => setEndAt(e.target.value)} />
                    </div>
                </div>

                <div className="ec-field-group">
                    <label>{t('boost.parameters')}</label>
                    <div className="ec-egg-list">
                        {params.map((param) => {
                            const id = identity(param);
                            const on = selected.has(id);

                            return (
                                <div key={id} className="ec-server-row ec-check-row" style={{ cursor: 'default' }}>
                                    <label className="ec-row ec-grow" style={{ cursor: 'pointer' }}>
                                        <input type="checkbox" checked={on} onChange={() => toggle(id)} />
                                        <span className="ec-truncate">{param.label}</span>
                                    </label>
                                    {on && (
                                        <Input
                                            className="ec-input-narrow"
                                            type="number"
                                            placeholder={t('boost.max_cap')}
                                            value={caps[id] ?? ''}
                                            onChange={(e) => setCaps((c) => ({ ...c, [id]: e.target.value }))}
                                        />
                                    )}
                                </div>
                            );
                        })}
                        {params.length === 0 && <div className="ec-empty">{t('boost.no_boostable')}</div>}
                    </div>
                </div>

                <div className="ec-row ec-secondary">
                    <AlertTriangle size={15} /> <span className="ec-field-desc">{t('boost.restart_warning')}</span>
                </div>
            </div>
        </Dialog>
    );
}
