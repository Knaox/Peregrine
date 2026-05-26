import { memo } from 'react';
import { useCountUp } from '@/hooks/useCountUp';
import { formatCpu } from '@/utils/format';

interface BiomeGaugeProps {
    /** Current CPU load (%, can exceed 100 on multi-core; undefined while loading). */
    cpu: number | undefined;
    /** Small caption under the value, e.g. "CPU". */
    label: string;
    /** Ring colour (CSS var). */
    to: string;
    /** True before the first stats poll — render a skeleton ring. */
    loading: boolean;
    /** Only count up when the server is actually running. */
    live: boolean;
    /** Outer diameter in px. */
    size?: number;
}

/**
 * Focal CPU dial — a ring with a solid theme-coloured stroke, soft glow and an
 * animated sweep that counts up with the live value. Skeleton-pulses on load.
 */
function BiomeGaugeImpl({ cpu, label, to, loading, live, size = 88 }: BiomeGaugeProps) {
    const animated = useCountUp(cpu ?? 0, { enabled: live && !loading });
    const pct = Math.max(0, Math.min(100, animated));
    const stroke = 7;
    const r = (size - stroke) / 2;
    const c = size / 2;
    const circumference = 2 * Math.PI * r;
    const progress = circumference * (pct / 100);

    if (loading) {
        return (
            <div className="biome-skeleton shrink-0 rounded-full" style={{ width: size, height: size }} aria-hidden />
        );
    }

    return (
        <div className="relative flex shrink-0 flex-col items-center justify-center" style={{ width: size, height: size }} aria-hidden>
            <svg width={size} height={size} className="block -rotate-90">
                <circle cx={c} cy={c} r={r} fill="none" stroke="var(--color-border)" strokeWidth={stroke} />
                {/* Solid stroke (CSS var) — an SVG gradient with var()/color-mix
                    stops doesn't render reliably, leaving the arc invisible. */}
                <circle
                    cx={c} cy={c} r={r} fill="none"
                    stroke={to}
                    strokeWidth={stroke}
                    strokeLinecap="round"
                    strokeDasharray={`${progress} ${circumference}`}
                    style={{ filter: `drop-shadow(0 0 6px ${to})` }}
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="font-mono text-base font-bold tabular-nums leading-none text-[var(--color-text-primary)]">
                    {cpu === undefined ? '—' : formatCpu(animated)}
                </span>
                <span className="mt-1 text-[8px] font-bold uppercase tracking-[0.22em] text-[var(--color-text-muted)]">{label}</span>
            </div>
        </div>
    );
}

export const BiomeGauge = memo(BiomeGaugeImpl);
