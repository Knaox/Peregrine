import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useSchedules } from '@/hooks/useSchedules';
import { formatDate } from '@/utils/format';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';
import type { Schedule } from '@/types/Schedule';

const INPUT_CLS = 'w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none';

function AddTaskForm({ serverId, scheduleId, onDone }: { serverId: number; scheduleId: number; onDone: () => void }) {
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
        <div className="mt-3 rounded-[var(--radius)] p-3" style={{ background: 'rgba(255,255,255,0.03)', border: '1px solid var(--color-border)' }}>
            <p className="mb-3 text-xs font-semibold text-[var(--color-text-secondary)]">{t('servers.schedules.add_task')}</p>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div>
                    <label className="mb-1 block text-xs text-[var(--color-text-muted)]">{t('servers.schedules.task_action')}</label>
                    <select value={action} onChange={(e) => setAction(e.target.value as 'command' | 'power' | 'backup')} className={INPUT_CLS}>
                        <option value="command">{t('servers.schedules.task_command')}</option>
                        <option value="power">{t('servers.schedules.task_power')}</option>
                        <option value="backup">{t('servers.schedules.task_backup')}</option>
                    </select>
                </div>
                {action !== 'backup' && (
                    <div className="md:col-span-2">
                        <label className="mb-1 block text-xs text-[var(--color-text-muted)]">{t('servers.schedules.task_payload')}</label>
                        <input
                            value={payload}
                            onChange={(e) => setPayload(e.target.value)}
                            placeholder={action === 'command' ? 'say Hello!' : 'start / stop / restart / kill'}
                            className={INPUT_CLS}
                            style={{ fontFamily: 'var(--font-mono)' }}
                        />
                    </div>
                )}
                <div>
                    <label className="mb-1 block text-xs text-[var(--color-text-muted)]">{t('servers.schedules.task_offset')}</label>
                    <input type="number" min={0} max={900} value={offset} onChange={(e) => setOffset(Number(e.target.value))} className={INPUT_CLS} />
                </div>
            </div>
            <div className="mt-3 flex justify-end gap-2">
                <Button variant="ghost" size="sm" onClick={onDone}>{t('common.cancel')}</Button>
                <Button variant="primary" size="sm" isLoading={addTask.isPending} onClick={handleSubmit}>{t('servers.schedules.add_task')}</Button>
            </div>
        </div>
    );
}

function ScheduleCard({ schedule, serverId }: { schedule: Schedule; serverId: number }) {
    const { t } = useTranslation();
    const { execute, remove, removeTask } = useSchedules(serverId);
    const [expanded, setExpanded] = useState(false);
    const [showAddTask, setShowAddTask] = useState(false);

    const actionLabel = (action: string) => {
        if (action === 'command') return t('servers.schedules.task_command');
        if (action === 'power') return t('servers.schedules.task_power');
        return t('servers.schedules.task_backup');
    };

    return (
        <div style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border)', borderRadius: 'var(--radius-lg)', padding: 16 }}>
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="space-y-1">
                    <div className="flex items-center gap-2">
                        <p className="text-sm font-semibold text-[var(--color-text-primary)]">{schedule.name}</p>
                        <span className={`rounded-[var(--radius-sm)] px-1.5 py-0.5 text-[10px] font-medium ${schedule.is_active ? 'bg-[var(--color-success)]/15 text-[var(--color-success)]' : 'bg-[var(--color-text-muted)]/15 text-[var(--color-text-muted)]'}`}>
                            {schedule.is_active ? t('servers.schedules.active') : 'Inactive'}
                        </span>
                    </div>
                    <p className="text-xs text-[var(--color-text-muted)]" style={{ fontFamily: 'var(--font-mono)' }}>
                        {schedule.minute} {schedule.hour} {schedule.day_of_month} {schedule.month} {schedule.day_of_week}
                    </p>
                    <p className="text-xs text-[var(--color-text-muted)]">
                        {schedule.next_run_at && <>{t('servers.schedules.next_run')}: {formatDate(schedule.next_run_at)}</>}
                        {schedule.last_run_at && <> &middot; {t('servers.schedules.last_run')}: {formatDate(schedule.last_run_at)}</>}
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <Button variant="secondary" size="sm" onClick={() => setExpanded(!expanded)}>
                        {t('servers.schedules.tasks')} ({(schedule.tasks ?? []).length})
                    </Button>
                    <Button variant="secondary" size="sm" isLoading={execute.isPending} onClick={() => execute.mutate(schedule.id)}>
                        {t('servers.schedules.run_now')}
                    </Button>
                    <Button variant="danger" size="sm" isLoading={remove.isPending} onClick={() => {
                        if (window.confirm(t('servers.schedules.confirm_delete', { name: schedule.name }))) remove.mutate(schedule.id);
                    }}>{t('servers.schedules.delete')}</Button>
                </div>
            </div>

            {expanded && (
                <div className="mt-4 space-y-2 border-t pt-4" style={{ borderColor: 'var(--color-border)' }}>
                    {(schedule.tasks ?? []).map((task) => (
                        <div key={task.id} className="flex items-center justify-between rounded-[var(--radius)] px-3 py-2" style={{ background: 'rgba(255,255,255,0.03)' }}>
                            <div className="flex items-center gap-3">
                                <span className="rounded-[var(--radius-sm)] bg-[var(--color-primary)]/10 px-2 py-0.5 text-[10px] font-medium text-[var(--color-primary)]">
                                    {actionLabel(task.action)}
                                </span>
                                {task.payload && <code className="text-xs text-[var(--color-text-secondary)]" style={{ fontFamily: 'var(--font-mono)' }}>{task.payload}</code>}
                                {task.time_offset > 0 && <span className="text-[10px] text-[var(--color-text-muted)]">+{task.time_offset}s</span>}
                            </div>
                            <Button variant="ghost" size="sm" isLoading={removeTask.isPending} onClick={() => removeTask.mutate({ scheduleId: schedule.id, taskId: task.id })}>
                                {t('servers.schedules.task_delete')}
                            </Button>
                        </div>
                    ))}

                    {showAddTask ? (
                        <AddTaskForm serverId={serverId} scheduleId={schedule.id} onDone={() => setShowAddTask(false)} />
                    ) : (
                        <Button variant="secondary" size="sm" onClick={() => setShowAddTask(true)}>
                            + {t('servers.schedules.add_task')}
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}

export function ServerSchedulesPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: schedules, isLoading, create, addTask } = useSchedules(serverId);

    const [showCreate, setShowCreate] = useState(false);
    const [preset, setPreset] = useState('restart_daily');
    const [showAdvanced, setShowAdvanced] = useState(false);
    const [form, setForm] = useState({ name: '', minute: '0', hour: '4', day_of_month: '*', month: '*', day_of_week: '*', is_active: true, only_when_online: true });

    interface PresetDef {
        name: string; minute: string; hour: string; day_of_month: string; month: string; day_of_week: string;
        task?: { action: string; payload: string; time_offset: number };
    }
    const PRESETS: Record<string, PresetDef> = {
        restart_daily: { name: t('servers.schedules.preset_restart_daily'), minute: '0', hour: '4', day_of_month: '*', month: '*', day_of_week: '*', task: { action: 'power', payload: 'restart', time_offset: 0 } },
        restart_12h: { name: t('servers.schedules.preset_restart_12h'), minute: '0', hour: '*/12', day_of_month: '*', month: '*', day_of_week: '*', task: { action: 'power', payload: 'restart', time_offset: 0 } },
        backup_daily: { name: t('servers.schedules.preset_backup_daily'), minute: '0', hour: '3', day_of_month: '*', month: '*', day_of_week: '*', task: { action: 'backup', payload: '', time_offset: 0 } },
        backup_weekly: { name: t('servers.schedules.preset_backup_weekly'), minute: '0', hour: '3', day_of_month: '*', month: '*', day_of_week: '0', task: { action: 'backup', payload: '', time_offset: 0 } },
    };

    const applyPreset = (key: string) => {
        setPreset(key);
        if (key !== 'custom') {
            const p = PRESETS[key];
            if (p) setForm((prev) => ({ ...prev, ...p }));
            setShowAdvanced(false);
        } else {
            setShowAdvanced(true);
        }
    };

    const handleCreate = () => {
        const currentPreset = PRESETS[preset];
        create.mutate(form, {
            onSuccess: (newSchedule) => {
                // Auto-add the preset's task if defined
                if (currentPreset?.task && newSchedule?.id) {
                    addTask.mutate({ scheduleId: newSchedule.id, payload: currentPreset.task });
                }
                setShowCreate(false);
                setPreset('restart_daily');
                setShowAdvanced(false);
                setForm({ name: '', minute: '0', hour: '4', day_of_month: '*', month: '*', day_of_week: '*', is_active: true, only_when_online: true });
            },
        });
    };

    const updateForm = (key: string, value: string | boolean) => setForm((prev) => ({ ...prev, [key]: value }));

    if (isLoading) return <div className="flex justify-center py-12"><Spinner size="lg" /></div>;

    return (
        <m.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }} className="space-y-6">
            <div className="flex items-center justify-between">
                <h2 className="text-xl font-bold text-[var(--color-text-primary)]">{t('servers.schedules.title')}</h2>
                <Button variant="primary" size="sm" onClick={() => setShowCreate(!showCreate)}>{t('servers.schedules.create')}</Button>
            </div>

            {showCreate && (
                <div style={{ background: 'var(--color-surface)', border: '1px solid var(--color-border)', borderRadius: 'var(--radius-lg)', padding: 20 }}>
                    <div className="space-y-4">
                        {/* Name */}
                        <div>
                            <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('servers.schedules.name')}</label>
                            <input value={form.name} onChange={(e) => updateForm('name', e.target.value)} placeholder={t('servers.schedules.name_placeholder')} className={INPUT_CLS} />
                        </div>

                        {/* Preset selector */}
                        <div>
                            <label className="mb-2 block text-sm text-[var(--color-text-secondary)]">{t('servers.schedules.preset_label')}</label>
                            <div className="grid grid-cols-2 gap-2 md:grid-cols-3">
                                {Object.entries(PRESETS).map(([key, p]) => (
                                    <button
                                        key={key}
                                        type="button"
                                        onClick={() => applyPreset(key)}
                                        className={`rounded-[var(--radius)] px-3 py-2.5 text-sm font-medium transition-all duration-150 ${
                                            preset === key
                                                ? 'bg-[var(--color-primary)]/15 text-[var(--color-primary)] ring-1 ring-[var(--color-primary)]/30'
                                                : 'bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'
                                        }`}
                                    >
                                        {t(`servers.schedules.preset_${key}`)}
                                    </button>
                                ))}
                                <button
                                    type="button"
                                    onClick={() => applyPreset('custom')}
                                    className={`rounded-[var(--radius)] px-3 py-2.5 text-sm font-medium transition-all duration-150 ${
                                        preset === 'custom'
                                            ? 'bg-[var(--color-primary)]/15 text-[var(--color-primary)] ring-1 ring-[var(--color-primary)]/30'
                                            : 'bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'
                                    }`}
                                >
                                    {t('servers.schedules.preset_custom')}
                                </button>
                            </div>
                        </div>

                        {/* Advanced cron fields (shown when custom or toggled) */}
                        {!showAdvanced && preset !== 'custom' && (
                            <button type="button" onClick={() => setShowAdvanced(true)} className="text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)] transition-colors">
                                {t('servers.schedules.preset_custom')} &rarr;
                            </button>
                        )}

                        {showAdvanced && (
                            <div className="grid grid-cols-5 gap-3 rounded-[var(--radius)] p-3" style={{ background: 'rgba(255,255,255,0.02)', border: '1px dashed var(--color-border)' }}>
                                {(['minute', 'hour', 'day_of_month', 'month', 'day_of_week'] as const).map((field) => (
                                    <div key={field}>
                                        <label className="mb-1 block text-[10px] uppercase tracking-wider text-[var(--color-text-muted)]">{t(`servers.schedules.${field}`)}</label>
                                        <input value={form[field]} onChange={(e) => updateForm(field, e.target.value)} className={INPUT_CLS} style={{ fontFamily: 'var(--font-mono)', fontSize: 13 }} />
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Toggles + submit */}
                        <div className="flex flex-wrap items-center gap-4">
                            <label className="flex items-center gap-2 text-sm text-[var(--color-text-secondary)]">
                                <input type="checkbox" checked={form.is_active} onChange={(e) => updateForm('is_active', e.target.checked)} /> {t('servers.schedules.active')}
                            </label>
                            <label className="flex items-center gap-2 text-sm text-[var(--color-text-secondary)]">
                                <input type="checkbox" checked={form.only_when_online} onChange={(e) => updateForm('only_when_online', e.target.checked)} /> {t('servers.schedules.only_when_online')}
                            </label>
                            <div className="ml-auto flex gap-2">
                                <Button variant="ghost" size="sm" onClick={() => setShowCreate(false)}>{t('common.cancel')}</Button>
                                <Button variant="primary" size="sm" isLoading={create.isPending} onClick={handleCreate}>{t('servers.schedules.create')}</Button>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {(!schedules || schedules.length === 0) ? (
                <p className="py-8 text-center text-[var(--color-text-muted)]">{t('servers.schedules.no_schedules')}</p>
            ) : (
                <div className="space-y-3">
                    {schedules.map((s) => <ScheduleCard key={s.id} schedule={s} serverId={serverId} />)}
                </div>
            )}
        </m.div>
    );
}
