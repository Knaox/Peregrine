import { useState } from 'react';
import { toLocalInput } from '../../lib/format';
import { useT } from '../../lib/i18n';
import type { ApiError } from '../../shared';
import { Button } from '../../ui/Button';
import { Callout } from '../../ui/Callout';
import { Dialog } from '../../ui/Dialog';
import { Input } from '../../ui/inputs';
import { Badge } from '../../ui/surfaces';
import { useToast } from '../../ui/Toast';
import { RecurrenceField } from './RecurrenceField';
import { useCreateBoost, useUpdateBoost, type Boost, type CreateBoostPayload, type Recurrence } from './useBoosts';

export interface BoostableParam {
    template_id: string;
    file_id: string;
    section: string | null;
    key: string;
    label: string;
    max?: number;
    /** Divide the value by the multiplier instead of multiplying it (deboost). */
    invert?: boolean;
}

interface ParamRow {
    file_id: string;
    section: string | null;
    key: string;
    label: string;
    max?: number;
    cap: string;
    invert: boolean;
}

interface Props {
    open: boolean;
    onClose: () => void;
    serverId: number;
    /** Parameters ticked in the editor — what a NEW boost will apply to. */
    selected: BoostableParam[];
    /** When set, the dialog edits this still-pending boost instead of creating one. */
    editing?: Boost | null;
    onSaved: () => void;
}

const keyOf = (p: { file_id: string; section: string | null; key: string }): string =>
    `${p.file_id}:${p.section ?? ''}:${p.key}`;

/**
 * Boost configuration: multiplier, active window, optional recurrence and a
 * per-parameter ceiling. Parameters for a new boost are ticked in the editor;
 * in edit mode the existing boost's parameters are shown read-only with their
 * caps. The parent remounts this dialog (keyed) so state loads from `editing`.
 */
export function BoostDialog({ open, onClose, serverId, selected, editing, onSaved }: Props) {
    const { t } = useT();
    const toast = useToast();
    const create = useCreateBoost(serverId);
    const update = useUpdateBoost(serverId);
    const isEdit = editing != null;

    const rows: ParamRow[] = isEdit
        ? editing.parameters.map((p) => ({ file_id: p.file_id, section: p.section, key: p.key, label: p.key, cap: p.max_cap != null ? String(p.max_cap) : '', invert: p.invert ?? false }))
        : selected.map((p) => ({ file_id: p.file_id, section: p.section, key: p.key, label: p.label, max: p.max, cap: '', invert: p.invert ?? false }));

    const now = new Date();
    const [multiplier, setMultiplier] = useState(isEdit ? String(editing.multiplier) : '2');
    const [startAt, setStartAt] = useState(isEdit ? toLocalInput(new Date(editing.start_at)) : toLocalInput(now));
    const [endAt, setEndAt] = useState(isEdit ? toLocalInput(new Date(editing.end_at)) : toLocalInput(new Date(now.getTime() + 86_400_000)));
    const [recurrence, setRecurrence] = useState<Recurrence | null>(isEdit ? editing.recurrence : null);
    const [until, setUntil] = useState(isEdit && editing.recurrence_until ? toLocalInput(new Date(editing.recurrence_until)) : '');
    const [caps, setCaps] = useState<Record<string, string>>(() => Object.fromEntries(rows.map((r) => [keyOf(r), r.cap])));

    const pending = create.isPending || update.isPending;

    const submit = (): void => {
        if (rows.length === 0 || Number(multiplier) <= 0) {
            toast.error(t('boost.invalid'));

            return;
        }

        const payload: CreateBoostPayload = {
            template_id: isEdit ? editing.template_id : (selected[0]?.template_id ?? ''),
            multiplier: Number(multiplier),
            start_at: new Date(startAt).toISOString(),
            end_at: new Date(endAt).toISOString(),
            recurrence,
            recurrence_until: recurrence !== null && until !== '' ? new Date(until).toISOString() : null,
            parameters: rows.map((r) => {
                const raw = caps[keyOf(r)] ?? '';
                // The per-parameter ceiling only applies when multiplying.
                return { file_id: r.file_id, section: r.section, key: r.key, max_cap: r.invert || raw === '' ? null : Number(raw), invert: r.invert };
            }),
        };

        const onError = (error: unknown): void => {
            const apiError = error as unknown as ApiError;
            toast.error(apiError.code === 'boost_overlap' ? t('boost.overlap') : t(isEdit ? 'boost.update_failed' : 'boost.create_failed'));
        };
        const onOk = (): void => {
            toast.success(t(isEdit ? 'boost.updated' : 'boost.created'));
            onSaved();
            onClose();
        };

        if (isEdit) {
            update.mutate({ boostId: editing.id, payload }, { onSuccess: onOk, onError });
        } else {
            create.mutate(payload, { onSuccess: onOk, onError });
        }
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            closeLabel={t('common.close')}
            title={t(isEdit ? 'boost.edit_title' : 'boost.new_title')}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button>
                    <Button loading={pending} disabled={rows.length === 0} onClick={submit}>
                        {t(isEdit ? 'boost.update' : 'boost.schedule')}
                    </Button>
                </>
            }
        >
            <div className="ec-dialog-body">
                <Callout variant="warning">{t('boost.auto_restart_warning')}</Callout>
                <p className="ec-field-desc ec-muted">{t('boost.config_intro', { count: rows.length })}</p>

                <div className="ec-field-group">
                    <label>{t('boost.multiplier')}</label>
                    <Input type="number" min={0} step="0.1" value={multiplier} onChange={(e) => setMultiplier(e.target.value)} />
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

                <RecurrenceField recurrence={recurrence} until={until} onRecurrenceChange={setRecurrence} onUntilChange={setUntil} />

                <div className="ec-field-group">
                    <label>{t('boost.caps_title')}</label>
                    <span className="ec-field-desc ec-muted">{t('boost.max_cap_hint')}</span>
                    <div className="ec-list">
                        {rows.map((r) => (
                            <div key={keyOf(r)} className="ec-between" style={{ gap: '0.75rem' }}>
                                <span className="ec-truncate">{r.label}</span>
                                <span className="ec-row" style={{ flexShrink: 0, gap: '0.5rem' }}>
                                    <Badge variant={r.invert ? 'info' : 'accent'}>
                                        {r.invert ? t('boost.factor_divide') : t('boost.factor_multiply')}{multiplier}
                                    </Badge>
                                    <span style={{ width: '7rem' }}>
                                        <Input
                                            type="number"
                                            min={0}
                                            step="0.1"
                                            disabled={r.invert}
                                            value={r.invert ? '' : (caps[keyOf(r)] ?? '')}
                                            placeholder={r.invert ? t('boost.cap_na') : (r.max != null ? String(r.max) : t('boost.cap_none'))}
                                            onChange={(e) => setCaps((c) => ({ ...c, [keyOf(r)]: e.target.value }))}
                                        />
                                    </span>
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </Dialog>
    );
}
