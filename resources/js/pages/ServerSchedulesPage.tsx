import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { useSchedules } from '@/hooks/useSchedules';
import { useServer } from '@/hooks/useServer';
import { useServerPermissions } from '@/hooks/useServerPermissions';
import { Spinner } from '@/components/ui/Spinner';
import { Button } from '@/components/ui/Button';
import { ScheduleCard } from '@/components/server/ScheduleCard';
import { withServerConflictGate } from '@/components/server/withServerConflictGate';
import { useNamespace } from '@/i18n/useNamespace';

const INPUT_CLS = 'w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none';

const stagger = { animate: { transition: { staggerChildren: 0.07 } } };

/* Inline SVG icons */
function ClockIcon({ className = 'size-5' }: { className?: string }) {
    useNamespace(["server-schedules"] as const);
    return (
        <svg className={className} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
        </svg>
    );
}

function RefreshIcon() {
    return (
        <svg className="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
        </svg>
    );
}

function ArchiveIcon() {
    return (
        <svg className="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <polyline points="21 8 21 21 3 21 3 8" /><rect x="1" y="3" width="22" height="5" /><line x1="10" y1="12" x2="14" y2="12" />
        </svg>
    );
}

function SettingsIcon() {
    return (
        <svg className="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
        </svg>
    );
}

function presetIcon(key: string) {
    if (key.startsWith('restart')) return <RefreshIcon />;
    if (key.startsWith('backup')) return <ArchiveIcon />;
    return <SettingsIcon />;
}

function ServerSchedulesPageImpl() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);
    const { data: schedules, isLoading, create, addTask } = useSchedules(serverId);
    const { data: server } = useServer(serverId);
    const perms = useServerPermissions(server);
    const canCreate = perms.has('schedule.create');

    const [showCreate, setShowCreate] = useState(false);
    const [preset, setPreset] = useState('restart_daily');
    const [showAdvanced, setShowAdvanced] = useState(false);
    const [form, setForm] = useState({ name: '', minute: '0', hour: '4', day_of_month: '*', month: '*', day_of_week: '*', is_active: true, only_when_online: true });

    interface PresetDef {
        name: string; minute: string; hour: string; day_of_month: string; month: string; day_of_week: string;
        task?: { action: string; payload: string; time_offset: number };
    }
    const PRESETS: Record<string, PresetDef> = {
        restart_daily: { name: t('server-schedules:schedules.preset_restart_daily'), minute: '0', hour: '4', day_of_month: '*', month: '*', day_of_week: '*', task: { action: 'power', payload: 'restart', time_offset: 0 } },
        restart_12h: { name: t('server-schedules:schedules.preset_restart_12h'), minute: '0', hour: '*/12', day_of_month: '*', month: '*', day_of_week: '*', task: { action: 'power', payload: 'restart', time_offset: 0 } },
        backup_daily: { name: t('server-schedules:schedules.preset_backup_daily'), minute: '0', hour: '3', day_of_month: '*', month: '*', day_of_week: '*', task: { action: 'backup', payload: '', time_offset: 0 } },
        backup_weekly: { name: t('server-schedules:schedules.preset_backup_weekly'), minute: '0', hour: '3', day_of_month: '*', month: '*', day_of_week: '0', task: { action: 'backup', payload: '', time_offset: 0 } },
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

    const handleCreate = async () => {
        const currentPreset = PRESETS[preset];
        const payload = { ...form };
        if (!payload.name.trim() && currentPreset) {
            payload.name = currentPreset.name;
        }
        try {
            // Sequential, not parallel: create's auto-invalidation would refetch
            // the schedule list before the preset task exists, and that stale
            // refetch (no task) raced ahead of the task one — so the new task
            // only showed up after a manual refresh. Awaiting the task creation
            // makes addTask's invalidation the last refetch, which wins.
            const newSchedule = await create.mutateAsync(payload);
            if (currentPreset?.task && newSchedule?.id) {
                await addTask.mutateAsync({ scheduleId: newSchedule.id, payload: currentPreset.task });
            }
            setShowCreate(false);
            setPreset('restart_daily');
            setShowAdvanced(false);
            setForm({ name: '', minute: '0', hour: '4', day_of_month: '*', month: '*', day_of_week: '*', is_active: true, only_when_online: true });
        } catch {
            // create/addTask expose their own error state; keep the form open
            // so the user can retry without re-entering everything.
        }
    };

    const updateForm = (key: string, value: string | boolean) => setForm((prev) => ({ ...prev, [key]: value }));

    if (isLoading) return <div className="flex justify-center py-12"><Spinner size="lg" /></div>;

    return (
        <m.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }} className="space-y-6">
            {/* Header with clock icon */}
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="flex size-9 items-center justify-center rounded-[var(--radius)] bg-[var(--color-primary)]/10">
                        <ClockIcon className="size-5 text-[var(--color-primary)]" />
                    </div>
                    <h2 className="text-xl font-bold text-[var(--color-text-primary)]">{t('server-schedules:schedules.title')}</h2>
                </div>
                {canCreate && (
                    <Button variant="primary" size="sm" onClick={() => setShowCreate(!showCreate)}>{t('server-schedules:schedules.create')}</Button>
                )}
            </div>

            {/* Create form with AnimatePresence */}
            <AnimatePresence>
                {showCreate && (
                    <m.div
                        initial={{ opacity: 0, y: -12, scale: 0.98 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: -12, scale: 0.98 }}
                        transition={{ duration: 0.3, ease: 'easeOut' }}
                        className="glass-card-enhanced rounded-[var(--radius-lg)] p-5"
                    >
                        <div className="space-y-4">
                            {/* Name */}
                            <div>
                                <label className="mb-1 block text-sm text-[var(--color-text-secondary)]">{t('server-schedules:schedules.name')}</label>
                                <input value={form.name} onChange={(e) => updateForm('name', e.target.value)} placeholder={t('server-schedules:schedules.name_placeholder')} className={INPUT_CLS} />
                            </div>

                            {/* Preset selector with icons */}
                            <div>
                                <label className="mb-2 block text-sm text-[var(--color-text-secondary)]">{t('server-schedules:schedules.preset_label')}</label>
                                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3">
                                    {Object.entries(PRESETS).map(([key]) => (
                                        <button
                                            key={key}
                                            type="button"
                                            onClick={() => applyPreset(key)}
                                            className={`inline-flex cursor-pointer items-center justify-center gap-2 rounded-[var(--radius)] px-3 py-2.5 text-sm font-medium transition-all duration-150 ${
                                                preset === key
                                                    ? 'bg-[var(--color-primary)]/15 text-[var(--color-primary)] ring-1 ring-[var(--color-primary)]/30'
                                                    : 'bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'
                                            }`}
                                        >
                                            {presetIcon(key)}
                                            {t(`server-schedules:schedules.preset_${key}`)}
                                        </button>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={() => applyPreset('custom')}
                                        className={`inline-flex cursor-pointer items-center justify-center gap-2 rounded-[var(--radius)] px-3 py-2.5 text-sm font-medium transition-all duration-150 ${
                                            preset === 'custom'
                                                ? 'bg-[var(--color-primary)]/15 text-[var(--color-primary)] ring-1 ring-[var(--color-primary)]/30'
                                                : 'bg-[var(--color-surface-hover)] text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)]'
                                        }`}
                                    >
                                        <SettingsIcon />
                                        {t('server-schedules:schedules.preset_custom')}
                                    </button>
                                </div>
                            </div>

                            {/* Toggle to show advanced cron fields */}
                            {!showAdvanced && preset !== 'custom' && (
                                <button type="button" onClick={() => setShowAdvanced(true)} className="cursor-pointer text-xs text-[var(--color-text-muted)] hover:text-[var(--color-text-secondary)] transition-colors">
                                    {t('server-schedules:schedules.preset_custom')} &rarr;
                                </button>
                            )}

                            {/* Advanced cron fields */}
                            <AnimatePresence>
                                {showAdvanced && (
                                    <m.div
                                        initial={{ opacity: 0, height: 0 }}
                                        animate={{ opacity: 1, height: 'auto' }}
                                        exit={{ opacity: 0, height: 0 }}
                                        transition={{ duration: 0.25, ease: 'easeInOut' }}
                                        className="overflow-hidden"
                                    >
                                        <div className="glass-card-enhanced rounded-[var(--radius)] p-3" style={{ border: '1px dashed var(--color-border)' }}>
                                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 md:grid-cols-5">
                                                {(['minute', 'hour', 'day_of_month', 'month', 'day_of_week'] as const).map((field) => (
                                                    <div key={field}>
                                                        <label className="mb-1 block text-[10px] uppercase tracking-wider text-[var(--color-text-muted)]">{t(`server-schedules:schedules.${field}`)}</label>
                                                        <input value={form[field]} onChange={(e) => updateForm(field, e.target.value)} className={INPUT_CLS} style={{ fontFamily: 'var(--font-mono)', fontSize: 13 }} />
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </m.div>
                                )}
                            </AnimatePresence>

                            {/* Toggles + submit */}
                            <div className="flex flex-wrap items-center gap-4">
                                <label className="flex cursor-pointer items-center gap-2 text-sm text-[var(--color-text-secondary)]">
                                    <input type="checkbox" checked={form.is_active} onChange={(e) => updateForm('is_active', e.target.checked)} /> {t('server-schedules:schedules.active')}
                                </label>
                                <label className="flex cursor-pointer items-center gap-2 text-sm text-[var(--color-text-secondary)]">
                                    <input type="checkbox" checked={form.only_when_online} onChange={(e) => updateForm('only_when_online', e.target.checked)} /> {t('server-schedules:schedules.only_when_online')}
                                </label>
                                <div className="ml-auto flex gap-2">
                                    <Button variant="ghost" size="sm" onClick={() => setShowCreate(false)}>{t('common:cancel')}</Button>
                                    <Button variant="primary" size="sm" isLoading={create.isPending} onClick={handleCreate}>{t('server-schedules:schedules.create')}</Button>
                                </div>
                            </div>
                        </div>
                    </m.div>
                )}
            </AnimatePresence>

            {/* Schedule list or empty state */}
            {(!schedules || schedules.length === 0) ? (
                <m.div
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    transition={{ duration: 0.4 }}
                    className="flex flex-col items-center gap-3 py-16 text-center"
                >
                    <div className="flex size-14 items-center justify-center rounded-[var(--radius-lg)] bg-[var(--color-surface)]" style={{ border: '1px solid var(--color-border)' }}>
                        <ClockIcon className="size-7 text-[var(--color-text-muted)]" />
                    </div>
                    <p className="text-sm text-[var(--color-text-muted)]">{t('server-schedules:schedules.no_schedules')}</p>
                </m.div>
            ) : (
                <m.div variants={stagger} initial="initial" animate="animate" className="space-y-3">
                    {schedules.map((s) => <ScheduleCard key={s.id} schedule={s} serverId={serverId} />)}
                </m.div>
            )}
        </m.div>
    );
}

// Conflict-state gate — see ServerFilesPage for the pattern.
export const ServerSchedulesPage = withServerConflictGate(ServerSchedulesPageImpl);
