import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { StepProps } from '../types';
import {
    startBackfill,
    getBackfillStatus,
    type BackfillResource,
} from '../services/setupApi';

export function BackfillStep({ onNext }: StepProps) {
    const { t } = useTranslation();
    const [resources, setResources] = useState<Record<string, BackfillResource>>({});
    const [allCompleted, setAllCompleted] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [hasStarted, setHasStarted] = useState(false);

    useEffect(() => {
        let mounted = true;

        const kickoff = async () => {
            if (! hasStarted) {
                setHasStarted(true);
                try {
                    await startBackfill();
                } catch (e: unknown) {
                    if (mounted) {
                        setError(e instanceof Error ? e.message : String(e));
                    }
                    return;
                }
            }
            const tick = async () => {
                if (! mounted) return;
                try {
                    const status = await getBackfillStatus();
                    if (! mounted) return;
                    setResources(status.resources);
                    setAllCompleted(status.all_completed);
                    if (! status.all_completed) {
                        setTimeout(tick, 2000);
                    }
                } catch (e: unknown) {
                    if (mounted) {
                        setError(e instanceof Error ? e.message : String(e));
                    }
                }
            };
            tick();
        };

        kickoff();
        return () => { mounted = false; };
    }, [hasStarted]);

    const labelFor = (key: string): string => t(`setup.backfill.resources.${key}`, { defaultValue: key });

    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-xl font-semibold text-[var(--color-text-primary)]">
                    {t('setup.backfill.title', { defaultValue: 'Synchronisation initiale' })}
                </h2>
                <p className="mt-2 text-sm text-[var(--color-text-secondary)]">
                    {t('setup.backfill.subtitle', { defaultValue: 'Peregrine importe les serveurs, utilisateurs, eggs et nodes existants depuis Pelican. Tu peux continuer le wizard pendant que ça tourne.' })}
                </p>
            </div>

            <div className="space-y-2">
                {Object.entries(resources).map(([key, r]) => (
                    <div key={key} className="flex items-center justify-between rounded-lg border border-[var(--color-glass-border)] bg-[var(--color-glass)] px-4 py-2 text-sm">
                        <span>{labelFor(key)}</span>
                        <span className={r.completed ? 'text-emerald-500' : 'text-amber-500'}>
                            {r.completed ? `✓ ${r.processed}` : `… ${r.processed}/${r.total || '?'}`}
                        </span>
                    </div>
                ))}
                {Object.keys(resources).length === 0 && (
                    <p className="text-sm text-[var(--color-text-secondary)]">
                        {t('setup.backfill.starting', { defaultValue: 'Démarrage…' })}
                    </p>
                )}
            </div>

            {error && (
                <div className="rounded-lg border border-rose-500/40 bg-rose-500/10 p-3 text-sm text-rose-500">
                    {error}
                </div>
            )}

            <div className="flex justify-end pt-4">
                {/* No Précédent — install already ran on the previous step,
                    going back would attempt a second install. Backfill is
                    idempotent, can run later via `php artisan pelican:backfill-mirrors`. */}
                <button
                    type="button"
                    onClick={onNext}
                    className="rounded-lg bg-[var(--color-primary)] px-5 py-2 text-sm font-medium text-white"
                >
                    {allCompleted
                        ? t('common.next', { defaultValue: 'Suivant' })
                        : t('setup.backfill.continue_anyway', { defaultValue: 'Continuer (synchro en arrière-plan)' })}
                </button>
            </div>
        </div>
    );
}
