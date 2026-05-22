import clsx from 'clsx';
import { useEffect, useMemo, useState } from 'react';
import { fieldKeyOf } from '../../lib/fieldKey';
import { useT } from '../../lib/i18n';
import type { ConfigTemplate } from '../../types';
import { Button } from '../../ui/Button';
import { Dialog } from '../../ui/Dialog';
import { Toggle } from '../../ui/inputs';
import { useToast } from '../../ui/Toast';
import { useBoosts } from '../boost/useBoosts';
import { CopyParams } from './CopyParams';
import { CopyReview } from './CopyReview';
import { CopyTargets } from './CopyTargets';
import { useCopyLog, useCopyTargets, useStartCopy, type CopyFilePayload } from './useCopy';

function buildFiles(templates: ConfigTemplate[], selected: Record<string, boolean>): CopyFilePayload[] {
    const out: CopyFilePayload[] = [];
    for (const template of templates) {
        for (const file of template.files) {
            const params = file.parameters
                .filter((param) => selected[fieldKeyOf(file.id, param)] ?? true)
                .map((param) => ({ key: param.key, section: param.section }));
            if (params.length > 0) {
                out.push({ id: file.id, params });
            }
        }
    }

    return out;
}

export function CopyDialog({ open, onClose, serverId, templates }: { open: boolean; onClose: () => void; serverId: number; templates: ConfigTemplate[] }) {
    const { t } = useT();
    const toast = useToast();
    const [step, setStep] = useState(1);
    const [targetsSel, setTargetsSel] = useState<Set<number>>(new Set());
    const [paramsSel, setParamsSel] = useState<Record<string, boolean>>({});
    const [batchId, setBatchId] = useState<string | null>(null);
    const [expected, setExpected] = useState(0);
    const [copyBoosts, setCopyBoosts] = useState(false);

    const targetsQuery = useCopyTargets(serverId, open);
    const sourceBoosts = useBoosts(serverId);
    const start = useStartCopy(serverId);
    const logQuery = useCopyLog(serverId, batchId, expected);

    const files = useMemo(() => buildFiles(templates, paramsSel), [templates, paramsSel]);
    const paramCount = files.reduce((total, file) => total + file.params.length, 0);
    const rows = logQuery.data ?? [];
    const done = batchId !== null && expected > 0 && rows.length >= expected;

    const targetNames = (targetsQuery.data ?? []).filter((s) => targetsSel.has(s.id)).map((s) => s.name);

    useEffect(() => {
        if (!done) {
            return;
        }
        const ok = rows.filter((row) => row.status === 'success').length;
        const fail = rows.length - ok;
        if (fail > 0) {
            toast.warning(t('copy.recap_partial', { ok, fail }));
        } else {
            toast.success(t('copy.recap_success', { ok }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [done]);

    const reset = (): void => {
        setStep(1);
        setTargetsSel(new Set());
        setParamsSel({});
        setBatchId(null);
        setExpected(0);
        setCopyBoosts(false);
    };

    const handleClose = (): void => {
        reset();
        onClose();
    };

    const toggleTarget = (id: number): void => {
        setTargetsSel((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const confirm = (): void => {
        start.mutate(
            { targets: [...targetsSel], files, copy_boosts: copyBoosts },
            {
                onSuccess: (data) => {
                    setBatchId(data.batch_id);
                    setExpected(data.targets);
                    toast.show(t('copy.in_progress'));
                },
                onError: () => toast.error(t('errors.generic')),
            },
        );
    };

    const footer = batchId !== null ? (
        <Button onClick={handleClose}>{t('common.close')}</Button>
    ) : step === 1 ? (
        <>
            <Button variant="ghost" onClick={handleClose}>{t('common.cancel')}</Button>
            <Button disabled={targetsSel.size === 0} onClick={() => setStep(2)}>{t('copy.next')}</Button>
        </>
    ) : step === 2 ? (
        <>
            <Button variant="ghost" onClick={() => setStep(1)}>{t('common.back')}</Button>
            <Button disabled={paramCount === 0} onClick={() => setStep(3)}>{t('copy.next')}</Button>
        </>
    ) : (
        <>
            <Button variant="ghost" onClick={() => setStep(2)}>{t('common.back')}</Button>
            <Button loading={start.isPending} onClick={confirm}>{t('copy.confirm')}</Button>
        </>
    );

    return (
        <Dialog open={open} onClose={handleClose} closeLabel={t('common.close')} title={t('copy.title')} size="lg" footer={footer}>
            <div className="ec-dialog-body">
                <div className="ec-steps">
                    {[1, 2, 3].map((index) => (
                        <span key={index} className="ec-row">
                            <span className={clsx('ec-step-dot', step >= index && 'ec-step-dot-active')}>{index}</span>
                            {index < 3 && <span className="ec-step-bar" />}
                        </span>
                    ))}
                </div>

                {step === 1 && <CopyTargets targets={targetsQuery.data ?? []} selected={targetsSel} onToggle={toggleTarget} loading={targetsQuery.isLoading} />}
                {step === 2 && <CopyParams templates={templates} selected={paramsSel} setSelected={setParamsSel} />}
                {step === 3 && (
                    <div className="ec-stack">
                        {batchId === null && (sourceBoosts.data?.length ?? 0) > 0 && (
                            <label className="ec-row" style={{ cursor: 'pointer' }}>
                                <Toggle checked={copyBoosts} onChange={setCopyBoosts} label={t('copy.copy_boosts')} />
                                <span className="ec-field-desc">{t('copy.copy_boosts')}</span>
                            </label>
                        )}
                        <CopyReview targetNames={targetNames} paramCount={paramCount} started={batchId !== null} rows={rows} expected={expected} done={done} />
                    </div>
                )}
            </div>
        </Dialog>
    );
}
