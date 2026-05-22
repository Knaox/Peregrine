import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { useSchedules } from '@/hooks/useSchedules';
import { Button } from '@/components/ui/Button';
import type { Schedule } from '@/types/Schedule';
import { useNamespace } from '@/i18n/useNamespace';

const INPUT_CLS = 'w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-surface-hover)] px-3 py-2 text-sm text-[var(--color-text-primary)] focus:border-[var(--color-primary)] focus:outline-none';

interface EditScheduleFormProps {
    serverId: number;
    schedule: Schedule;
    onDone: () => void;
}

/**
 * Inline editor for an existing schedule's settings (name, cron, active,
 * "only when online"). Reuses the `update` mutation; prefilled from the
 * schedule so a single field — e.g. only_when_online — can be flipped without
 * deleting and recreating the schedule.
 */
export function EditScheduleForm({ serverId, schedule, onDone }: EditScheduleFormProps) {
    useNamespace(["server-schedules"] as const);
    const { t } = useTranslation();
    const { update } = useSchedules(serverId);
    const [form, setForm] = useState({
        name: schedule.name,
        minute: schedule.minute,
        hour: schedule.hour,
        day_of_month: schedule.day_of_month,
        month: schedule.month,
        day_of_week: schedule.day_of_week,
        is_active: schedule.is_active,
        only_when_online: schedule.only_when_online,
    });

    const updateForm = (key: string, value: string | boolean) => setForm((prev) => ({ ...prev, [key]: value }));

    const handleSubmit = () => {
        update.mutate({ scheduleId: schedule.id, payload: form }, { onSuccess: onDone });
    };

    return (
        <m.div
            initial={{ opacity: 0, height: 0 }}
            animate={{ opacity: 1, height: 'auto' }}
            exit={{ opacity: 0, height: 0 }}
            transition={{ duration: 0.25, ease: 'easeInOut' }}
            className="overflow-hidden"
        >
            <div className="mt-3 glass-card-enhanced rounded-[var(--radius)] p-4 space-y-4">
                <div>
                    <label className="mb-1 block text-xs text-[var(--color-text-muted)]">{t('server-schedules:schedules.name')}</label>
                    <input value={form.name} onChange={(e) => updateForm('name', e.target.value)} className={INPUT_CLS} />
                </div>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 md:grid-cols-5">
                    {(['minute', 'hour', 'day_of_month', 'month', 'day_of_week'] as const).map((field) => (
                        <div key={field}>
                            <label className="mb-1 block text-[10px] uppercase tracking-wider text-[var(--color-text-muted)]">{t(`server-schedules:schedules.${field}`)}</label>
                            <input value={form[field]} onChange={(e) => updateForm(field, e.target.value)} className={INPUT_CLS} style={{ fontFamily: 'var(--font-mono)', fontSize: 13 }} />
                        </div>
                    ))}
                </div>
                <div className="flex flex-wrap items-center gap-4">
                    <label className="flex cursor-pointer items-center gap-2 text-sm text-[var(--color-text-secondary)]">
                        <input type="checkbox" checked={form.is_active} onChange={(e) => updateForm('is_active', e.target.checked)} /> {t('server-schedules:schedules.active')}
                    </label>
                    <label className="flex cursor-pointer items-center gap-2 text-sm text-[var(--color-text-secondary)]">
                        <input type="checkbox" checked={form.only_when_online} onChange={(e) => updateForm('only_when_online', e.target.checked)} /> {t('server-schedules:schedules.only_when_online')}
                    </label>
                    <div className="ml-auto flex gap-2">
                        <Button variant="ghost" size="sm" onClick={onDone}>{t('common:cancel')}</Button>
                        <Button variant="primary" size="sm" isLoading={update.isPending} onClick={handleSubmit}>{t('server-schedules:schedules.edit_schedule')}</Button>
                    </div>
                </div>
            </div>
        </m.div>
    );
}
