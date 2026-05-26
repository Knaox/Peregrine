import { memo } from 'react';
import { formatBytes } from '@/utils/format';

interface BiomeMetricBarProps {
    /** Short label, e.g. "RAM" / "Disk". */
    label: string;
    /** Current usage in bytes (undefined while stats load). */
    bytes: number | undefined;
    /** Quota in MB. 0 / undefined means "no limit". */
    limitMb: number;
    /** True before the first stats poll — render a shimmer skeleton. */
    loading: boolean;
    /** Only animate (count-up + fill grow) when the server is actually running. */
    live: boolean;
}

/**
 * Live resource meter — a calm, theme-driven fill that grows with a smooth CSS
 * width transition (no flashy sweeps) and counts its byte value up on poll.
 * Colour follows the parent theme's primary, shifting to warning/danger only
 * when the quota saturates. No-quota servers show a quiet baseline track.
 */
function BiomeMetricBarImpl({ label, bytes, limitMb, loading, live }: BiomeMetricBarProps) {
    const limitBytes = limitMb * 1024 * 1024;
    const hasLimit = limitMb > 0;
    const pct = hasLimit && bytes !== undefined ? Math.max(0, Math.min(100, (bytes / limitBytes) * 100)) : 0;
    const color = pct > 88 ? 'var(--color-danger)' : pct > 70 ? 'var(--color-warning)' : 'var(--color-primary)';

    return (
        <div className="flex flex-col gap-1">
            <div className="flex items-baseline justify-between">
                <span className="text-[9px] font-bold uppercase tracking-[0.18em] text-[var(--color-text-muted)]">{label}</span>
                {loading ? (
                    <span className="biome-skeleton h-2.5 w-10 rounded" />
                ) : (
                    // Show the real usage directly (no count-up indirection) so the
                    // value always tracks live consumption; the bar fill animates.
                    // With no quota, append "∞" so the value reads as uncapped.
                    <span className="font-mono text-[11px] font-semibold tabular-nums text-[var(--color-text-secondary)]">
                        {bytes === undefined ? '—' : formatBytes(bytes)}
                        {bytes !== undefined && !hasLimit && (
                            <span className="ml-1 text-[var(--color-text-muted)]">· ∞</span>
                        )}
                    </span>
                )}
            </div>
            <div className="h-1.5 w-full overflow-hidden rounded-[var(--radius-full)] bg-[var(--color-border)]/60">
                {loading ? (
                    <div className="biome-skeleton h-full w-full" />
                ) : hasLimit ? (
                    <div
                        className="h-full rounded-[var(--radius-full)]"
                        style={{
                            width: `${pct}%`,
                            background: `linear-gradient(90deg, color-mix(in srgb, ${color} 60%, transparent), ${color})`,
                            boxShadow: pct > 0 ? `0 0 8px color-mix(in srgb, ${color} 60%, transparent)` : 'none',
                            transition: live ? 'width 700ms cubic-bezier(0.22,1,0.36,1)' : 'none',
                        }}
                    />
                ) : (
                    // No quota — a faint striped track reads as "uncapped" rather
                    // than an empty (broken-looking) bar.
                    <div
                        className="h-full w-full rounded-[var(--radius-full)]"
                        style={{
                            background: 'repeating-linear-gradient(90deg, color-mix(in srgb, var(--color-primary) 24%, transparent) 0 5px, transparent 5px 11px)',
                        }}
                    />
                )}
            </div>
        </div>
    );
}

export const BiomeMetricBar = memo(BiomeMetricBarImpl);
