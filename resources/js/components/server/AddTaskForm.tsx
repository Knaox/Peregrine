import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { m, AnimatePresence } from 'motion/react';
import { useSchedules } from '@/hooks/useSchedules';
import { Button } from '@/components/ui/Button';

const INPUT_CLS = 'w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none';

/* SVG icons for action types */
function CommandIcon() {
    return (
        <svg className="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <polyline points="4 17 10 11 4 5" /><line x1="12" y1="19" x2="20" y2="19" />
        </svg>
    );
}

function PowerIcon() {
    return (
        <svg className="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <path d="M18.36 6.64a9 9 0 1 1-12.73 0" /><line x1="12" y1="2" x2="12" y2="12" />
        </svg>
    );
}

function BackupIcon() {
    return (
        <svg className="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <rect x="2" y="3" width="20" height="14" rx="2" /><line x1="8" y1="21" x2="16" y2="21" /><line x1="12" y1="17" x2="12" y2="21" />
        </svg>
    );
}

export function actionIcon(action: string) {
    if (action === 'command') return <CommandIcon />;
    if (action === 'power') return <PowerIcon />;
    return <BackupIcon />;
}

interface AddTaskFormProps {
    serverId: number;
    scheduleId: number;
    onDone: () => void;
}

export function AddTaskForm({ serverId, scheduleId, onDone }: AddTaskFormProps) {
    const { t } = useTranslation();
    const { addTask } = useSchedules(serverId);
    const [action, setAction] = useState<'command' | 'power' | 'backup'>('command');
    const [payload, setPayload] = useState('');
    const [offset, setOffset] = useState(0);

    const handleSubmit = () => {
        addTask.mutate(
            { scheduleId, payload: { action, payload: action === 'backup' ? '' : payload, time_offset: offset } },
            { onSuccess: () => { setPayload(''); setOffset(0); onDone(); } },
        );
    };

    return (
        <m.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            transition={{ duration: 0.25, ease: 'easeInOut' }}
            className="overflow-hidden"
        >
            <div className="mt-3 glass-card-enhanced rounded-[var(--radius)] p-4">
                <div className="mb-3 flex items-center gap-2">
                    <svg className="size-4 text-[var(--color-primary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
                        <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="16" /><line x1="8" y1="12" x2="16" y2="12" />
                    </svg>
                    <p className="text-xs font-semibold text-[var(--color-text-secondary)]">{t('servers.schedules.add_task')}</p>
                </div>
                <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                    <div>
                        <label className="mb-1 block text-xs text-[var(--color-text-muted)]">{t('servers.schedules.task_action')}</label>
                        <div className="relative">
                            <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-text-muted)]">
                                {actionIcon(action)}
                            </span>
                            <select value={action} onChange={(e) => setAction(e.target.value as 'command' | 'power' | 'backup')} className={`${INPUT_CLS} cursor-pointer pl-9`}>
                                <option value="command">{t('servers.schedules.task_command')}</option>
                                <option value="power">{t('servers.schedules.task_power')}</option>
                                <option value="backup">{t('servers.schedules.task_backup')}</option>
                            </select>
                        </div>
                    </div>
                    <AnimatePresence>
                        {action !== 'backup' && (
                            <m.div
                                className="md:col-span-2"
                                initial={{ opacity: 0, x: -8 }}
                                animate={{ opacity: 1, x: 0 }}
                                exit={{ opacity: 0, x: -8 }}
                                transition={{ duration: 0.2 }}
                            >
                                <label className="mb-1 block text-xs text-[var(--color-text-muted)]">{t('servers.schedules.task_payload')}</label>
                                <input
                                    value={payload}
                                    onChange={(e) => setPayload(e.target.value)}
                                    placeholder={action === 'command' ? 'say Hello!' : 'start / stop / restart / kill'}
                                    className={INPUT_CLS}
                                    style={{ fontFamily: 'var(--font-mono)' }}
                                />
                            </m.div>
                        )}
                    </AnimatePresence>
                    <div>
                        <label className="mb-1 block text-xs text-[var(--color-text-muted)]">{t('servers.schedules.task_offset')}</label>
                        <input type="number" min={0} max={900} value={offset} onChange={(e) => setOffset(Number(e.target.value))} className={INPUT_CLS} />
                    </div>
                </div>
                <div className="mt-4 flex justify-end gap-2">
                    <Button variant="ghost" size="sm" onClick={onDone}>{t('common.cancel')}</Button>
                    <Button variant="primary" size="sm" isLoading={addTask.isPending} onClick={handleSubmit}>{t('servers.schedules.add_task')}</Button>
                </div>
            </div>
        </m.div>
    );
}
