import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import { Button } from '@/components/ui/Button';
import { useServers } from '@/hooks/useServers';
import { useSchedules } from '@/hooks/useSchedules';
import type { Schedule } from '@/types/Schedule';
import type { Server } from '@/types/Server';
import type { CopyScheduleResult } from '@/services/scheduleApi';
import { useNamespace } from '@/i18n/useNamespace';

interface CopyScheduleDialogProps {
    serverId: number;
    schedule: Schedule;
    onClose: () => void;
}

/** A server is a valid copy target if the user owns it (permissions === null) or holds schedule.create. */
const canManageSchedules = (s: Server) =>
    (s.permissions ?? null) === null || (s.permissions ?? []).includes('schedule.create');

/**
 * Copies a schedule (cron + tasks) onto one or more of the user's OTHER
 * servers. Targets come from the host /api/servers list filtered to those the
 * user may create schedules on; the current server is excluded. Results are
 * reported per target so a single failure stays visible without blocking.
 */
export function CopyScheduleDialog({ serverId, schedule, onClose }: CopyScheduleDialogProps) {
    useNamespace(['server-schedules'] as const);
    const { t } = useTranslation();
    const { data: serversData } = useServers();
    const { copy } = useSchedules(serverId);

    const targets = useMemo<Server[]>(
        () => (serversData?.data ?? []).filter((s) => s.id !== serverId && !!s.identifier && canManageSchedules(s)),
        [serversData, serverId],
    );

    const [selected, setSelected] = useState<Set<number>>(new Set());
    const [results, setResults] = useState<CopyScheduleResult[] | null>(null);

    const allSelected = targets.length > 0 && selected.size === targets.length;

    const toggle = (id: number) =>
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id); else next.add(id);
            return next;
        });
    const toggleAll = () => setSelected(allSelected ? new Set() : new Set(targets.map((s) => s.id)));

    const submit = async () => {
        if (selected.size === 0) return;
        const res = await copy.mutateAsync({ scheduleId: schedule.id, targetServerIds: [...selected] });
        setResults(res);
    };

    return (
        <m.div
            initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
            className="fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-sm"
            style={{ background: 'var(--modal-scrim)' }}
            onClick={onClose}
        >
            <m.div
                initial={{ opacity: 0, scale: 0.95 }} animate={{ opacity: 1, scale: 1 }} exit={{ opacity: 0, scale: 0.95 }}
                transition={{ duration: 0.2 }}
                className="glass-card-enhanced w-full max-w-md max-h-[90vh] overflow-y-auto rounded-[var(--radius-lg)] p-5 space-y-4"
                onClick={(e) => e.stopPropagation()}
            >
                <div>
                    <h3 className="text-base font-semibold text-[var(--color-text-primary)]">
                        {t('server-schedules:schedules.copy_title')}
                    </h3>
                    <p className="mt-1 text-xs text-[var(--color-text-muted)]">
                        {t('server-schedules:schedules.copy_subtitle', { name: schedule.name })}
                    </p>
                </div>

                {results ? (
                    <>
                        <div className="space-y-1.5">
                            {results.map((r) => (
                                <div key={r.server_id} className="flex items-center justify-between gap-2 rounded-[var(--radius)] border border-[var(--color-border)] px-3 py-2">
                                    <span className="min-w-0 flex-1 truncate text-sm text-[var(--color-text-primary)]">{r.server_name}</span>
                                    <span className={`shrink-0 text-xs font-medium ${r.success ? 'text-[var(--color-success)]' : 'text-[var(--color-danger)]'}`}>
                                        {r.success ? t('server-schedules:schedules.copy_success') : (r.error || t('server-schedules:schedules.copy_failed'))}
                                    </span>
                                </div>
                            ))}
                        </div>
                        <div className="flex justify-end">
                            <Button onClick={onClose}>{t('server-schedules:schedules.copy_done')}</Button>
                        </div>
                    </>
                ) : targets.length === 0 ? (
                    <>
                        <p className="py-6 text-center text-sm text-[var(--color-text-muted)]">
                            {t('server-schedules:schedules.copy_no_targets')}
                        </p>
                        <div className="flex justify-end">
                            <Button variant="ghost" onClick={onClose}>{t('common:cancel')}</Button>
                        </div>
                    </>
                ) : (
                    <>
                        <button
                            type="button"
                            onClick={toggleAll}
                            className="flex w-full cursor-pointer items-center gap-2.5 rounded-[var(--radius)] border border-[var(--color-border)] px-3 py-2 text-left text-xs font-medium text-[var(--color-text-secondary)] transition-colors hover:bg-[var(--color-surface-hover)]"
                        >
                            <input type="checkbox" readOnly checked={allSelected} className="pointer-events-none" />
                            {t('server-schedules:schedules.copy_select_all')}
                        </button>
                        <div className="max-h-64 space-y-1.5 overflow-y-auto">
                            {targets.map((s) => (
                                <label key={s.id} className="flex cursor-pointer items-center gap-2.5 rounded-[var(--radius)] border border-[var(--color-border)] px-3 py-2 transition-colors hover:bg-[var(--color-surface-hover)]">
                                    <input type="checkbox" checked={selected.has(s.id)} onChange={() => toggle(s.id)} />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium text-[var(--color-text-primary)]">{s.name}</span>
                                        {s.egg?.name && <span className="block truncate text-xs text-[var(--color-text-muted)]">{s.egg.name}</span>}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button variant="ghost" onClick={onClose} disabled={copy.isPending}>{t('common:cancel')}</Button>
                            <Button onClick={submit} isLoading={copy.isPending} disabled={selected.size === 0}>
                                {t('server-schedules:schedules.copy_confirm', { n: selected.size })}
                            </Button>
                        </div>
                    </>
                )}
            </m.div>
        </m.div>
    );
}
