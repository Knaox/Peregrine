import { useState, useEffect } from 'react';
import { type StatBarProps } from '@/components/ui/StatBar.props';

function getBarColor(percent: number): string {
    if (percent < 50) return 'var(--color-success)';
    if (percent < 80) return 'var(--color-warning)';
    return 'var(--color-danger)';
}

function getBarGlowColor(percent: number): string {
    if (percent < 50) return 'var(--color-success-glow)';
    if (percent < 80) return 'rgba(var(--color-warning-rgb), 0.3)';
    return 'var(--color-danger-glow)';
}

export function StatBar({ label, value, max, formatted }: StatBarProps) {
    const percent = max > 0 ? (value / max) * 100 : 0;
    const clampedPercent = Math.min(100, Math.max(0, percent));
    const color = getBarColor(clampedPercent);
    const glowColor = getBarGlowColor(clampedPercent);

    const [animatedWidth, setAnimatedWidth] = useState(0);

    useEffect(() => {
        const timer = setTimeout(() => setAnimatedWidth(clampedPercent), 100);
        return () => clearTimeout(timer);
    }, [clampedPercent]);

    return (
        <div className="flex flex-col gap-1.5">
            {(label || formatted) && (
                <div className="flex items-center justify-between">
                    <span style={{ fontSize: 13, fontWeight: 400, color: 'var(--color-text-muted)' }}>
                        {label}
                    </span>
                    <span className="text-sm font-medium" style={{ color: 'var(--color-text-primary)' }}>
                        {formatted}
                    </span>
                </div>
            )}
            <div style={{
                height: 6,
                borderRadius: 3,
                background: 'var(--surface-overlay-strong)',
                overflow: 'hidden',
                position: 'relative',
            }}>
                <div
                    className="progress-bar-glow"
                    style={{
                        height: '100%',
                        width: `${animatedWidth}%`,
                        background: `linear-gradient(90deg, ${color}, ${color}cc)`,
                        borderRadius: 3,
                        transition: 'width 800ms cubic-bezier(0.34, 1.56, 0.64, 1), background 500ms ease',
                        boxShadow: clampedPercent > 0 ? `0 0 12px ${glowColor}, 0 1px 3px ${glowColor}` : 'none',
                    }}
                />
            </div>
        </div>
    );
}
