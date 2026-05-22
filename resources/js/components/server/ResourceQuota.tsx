import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';

interface ResourceQuotaProps {
    /** Resource name shown on the left (e.g. "Backups"). */
    label: string;
    /** How many are currently used. */
    used: number;
    /** Maximum allowed by the plan. */
    limit: number;
}

/**
 * Compact quota card: "used / limit", an animated bar, and a remaining /
 * limit-reached line. Colour shifts green → amber → red as the quota fills.
 * Rendered on the Backups / Databases / Network pages just under the header.
 */
export function ResourceQuota({ label, used, limit }: ResourceQuotaProps) {
    const { t } = useTranslation();

    const remaining = Math.max(0, limit - used);
    const atLimit = used >= limit;
    const percent = limit > 0 ? Math.min(100, (used / limit) * 100) : 100;
    const color = atLimit
        ? 'var(--color-danger)'
        : percent >= 80
            ? 'var(--color-warning)'
            : 'var(--color-success)';

    return (
        <div className="glass-card-enhanced rounded-[var(--radius-lg)] px-4 py-3">
            <div className="mb-2 flex items-center justify-between gap-2">
                <span className="text-xs font-medium uppercase tracking-wide text-[var(--color-text-muted)]">
                    {label}
                </span>
                <span className="text-sm font-semibold tabular-nums" style={{ color }}>
                    {used}
                    <span className="text-[var(--color-text-muted)]"> / {limit}</span>
                </span>
            </div>
            <div className="h-1.5 w-full overflow-hidden rounded-full bg-[var(--color-surface-hover)]">
                <m.div
                    className="h-full rounded-full"
                    style={{ background: color, boxShadow: `0 0 10px ${color}66` }}
                    initial={{ width: 0 }}
                    animate={{ width: `${percent}%` }}
                    transition={{ duration: 0.6, ease: [0.34, 1.56, 0.64, 1] }}
                />
            </div>
            <p
                className="mt-1.5 text-[11px] font-medium"
                style={{ color: atLimit ? 'var(--color-danger)' : 'var(--color-text-muted)' }}
            >
                {atLimit
                    ? t('common:quota.limit_reached')
                    : t('common:quota.remaining', { count: remaining })}
            </p>
        </div>
    );
}
