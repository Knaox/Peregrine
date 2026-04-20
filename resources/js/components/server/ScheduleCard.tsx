import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AnimatePresence, m } from 'motion/react';
import { useSchedules } from '@/hooks/useSchedules';
import { useServer } from '@/hooks/useServer';
import { useServerPermissions } from '@/hooks/useServerPermissions';
import { formatDate } from '@/utils/format';
import { Button } from '@/components/ui/Button';
import { AddTaskForm, actionIcon } from '@/components/server/AddTaskForm';
import type { Schedule } from '@/types/Schedule';

/* Inline SVG icons */
function ClockIconSmall() {
    return (
        <svg className="size-4 shrink-0 text-[var(--color-primary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
            <circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" />
        </svg>
    );
}

function ChevronIcon({ expanded }: { expanded: boolean }) {
    return (
        <m.svg
            className="size-4 text-[var(--color-text-muted)]"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            animate={{ rotate: expanded ? 180 : 0 }}
            transition={{ duration: 0.25, ease: 'easeInOut' }}
        >
            <polyline points="6 9 12 15 18 9" />
        </m.svg>
    );
}

export const fadeUp = { initial: { opacity: 0, y: 14 }, animate: { opacity: 1, y: 0, transition: { duration: 0.3 } } };

interface ScheduleCardProps {
    schedule: Schedule;
    serverId: number;
}

export function ScheduleCard({ schedule, serverId }: ScheduleCardProps) {
    const { t } = useTranslation();
    const { execute, remove, removeTask } = useSchedules(serverId);
    const { data: server } = useServer(serverId);
    const perms = useServerPermissions(server);
    const canUpdate = perms.has('schedule.update');
    const canDelete = perms.has('schedule.delete');
    const [expanded, setExpanded] = useState(false);
    const [showAddTask, setShowAddTask] = useState(false);

    const actionLabel = (action: string) => {
        if (action === 'command') return t('servers.schedules.task_command');
        if (action === 'power') return t('servers.schedules.task_power');
        return t('servers.schedules.task_backup');
    };

    const cronStr = `${schedule.minute} ${schedule.hour} ${schedule.day_of_month} ${schedule.month} ${schedule.day_of_week}`;
    const taskCount = (schedule.tasks ?? []).length;

    return (
        <m.div variants={fadeUp} className="glass-card-enhanced hover-lift rounded-[var(--radius-lg)] p-4">
            <div className="flex flex-col sm:flex-row sm:flex-wrap items-start sm:items-center justify-between gap-3 sm:gap-4">
                <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex items-center gap-2">
                        <ClockIconSmall />
                        <p className="truncate text-sm font-semibold text-[var(--color-text-primary)]">{schedule.name}</p>
                        <span className={`shrink-0 rounded-[var(--radius-sm)] px-1.5 py-0.5 text-[10px] font-medium ${schedule.is_active ? 'bg-[var(--color-success)]/15 text-[var(--color-success)]' : 'bg-[var(--color-text-muted)]/15 text-[var(--color-text-muted)]'}`}>
                            {schedule.is_active ? t('servers.schedules.active') : 'Inactive'}
                        </span>
                    </div>
                    {/* Cron expression in subtle highlighted box */}
                    <div className="inline-flex items-center gap-2 rounded-[var(--radius)] px-2.5 py-1" style={{ background: 'var(--color-primary-glow)', border: '1px solid rgba(var(--color-primary-rgb, 249 115 22), 0.15)' }}>
                        <svg className="size-3 text-[var(--color-primary)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={2.5} strokeLinecap="round" strokeLinejoin="round">
                            <polyline points="4 17 10 11 4 5" />
                        </svg>
                        <code className="text-xs font-medium text-[var(--color-primary)]" style={{ fontFamily: 'var(--font-mono)' }}>
                            {cronStr}
                        </code>
                    </div>
                    <p className="text-xs text-[var(--color-text-muted)]">
                        {schedule.next_run_at && <>{t('servers.schedules.next_run')}: {formatDate(schedule.next_run_at)}</>}
                        {schedule.last_run_at && <> &middot; {t('servers.schedules.last_run')}: {formatDate(schedule.last_run_at)}</>}
                    </p>
                </div>
                <div className="flex items-center gap-2 flex-wrap">
                    <button
                        type="button"
                        onClick={() => setExpanded(!expanded)}
                        className="inline-flex cursor-pointer items-center gap-1.5 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-1.5 text-xs font-medium text-[var(--color-text-primary)] transition-all hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-border-hover)]"
                    >
                        {t('servers.schedules.tasks')} ({taskCount})
                        <ChevronIcon expanded={expanded} />
                    </button>
                    {canUpdate && (
                        <Button variant="secondary" size="sm" isLoading={execute.isPending} onClick={() => execute.mutate(schedule.id)}>
                            {t('servers.schedules.run_now')}
                        </Button>
                    )}
                    {canDelete && (
                        <Button variant="danger" size="sm" isLoading={remove.isPending} onClick={() => {
                            if (window.confirm(t('servers.schedules.confirm_delete', { name: schedule.name }))) remove.mutate(schedule.id);
                        }}>{t('servers.schedules.delete')}</Button>
                    )}
                </div>
            </div>

            {/* Tasks expand/collapse */}
            <AnimatePresence>
                {expanded && (
                    <m.div
                        initial={{ opacity: 0, height: 0 }}
                        animate={{ opacity: 1, height: 'auto' }}
                        exit={{ opacity: 0, height: 0 }}
                        transition={{ duration: 0.3, ease: 'easeInOut' }}
                        className="overflow-hidden"
                    >
                        <div className="mt-4 space-y-2 border-t pt-4" style={{ borderColor: 'var(--color-border)' }}>
                            {(schedule.tasks ?? []).map((task) => (
                                <div key={task.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-[var(--radius)] px-3 py-2 glass-card-enhanced">
                                    <div className="flex items-center gap-2 sm:gap-3 flex-wrap">
                                        <span className="inline-flex items-center gap-1.5 rounded-[var(--radius-sm)] bg-[var(--color-primary)]/10 px-2 py-0.5 text-[10px] font-medium text-[var(--color-primary)]">
                                            {actionIcon(task.action)}
                                            {actionLabel(task.action)}
                                        </span>
                                        {task.payload && <code className="text-xs text-[var(--color-text-secondary)]" style={{ fontFamily: 'var(--font-mono)' }}>{task.payload}</code>}
                                        {task.time_offset > 0 && <span className="text-[10px] text-[var(--color-text-muted)]">+{task.time_offset}s</span>}
                                    </div>
                                    {canUpdate && (
                                        <Button variant="ghost" size="sm" isLoading={removeTask.isPending} onClick={() => removeTask.mutate({ scheduleId: schedule.id, taskId: task.id })}>
                                            {t('servers.schedules.task_delete')}
                                        </Button>
                                    )}
                                </div>
                            ))}

                            {canUpdate && (
                                <AnimatePresence>
                                    {showAddTask ? (
                                        <AddTaskForm serverId={serverId} scheduleId={schedule.id} onDone={() => setShowAddTask(false)} />
                                    ) : (
                                        <m.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
                                            <Button variant="secondary" size="sm" onClick={() => setShowAddTask(true)}>
                                                + {t('servers.schedules.add_task')}
                                            </Button>
                                        </m.div>
                                    )}
                                </AnimatePresence>
                            )}
                        </div>
                    </m.div>
                )}
            </AnimatePresence>
        </m.div>
    );
}
