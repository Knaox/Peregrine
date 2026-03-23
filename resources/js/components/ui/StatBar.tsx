import { useState, useEffect } from 'react';
import { type StatBarProps } from '@/components/ui/StatBar.props';

function getBarColor(percent: number): string {
    if (percent < 50) return 'var(--color-success)';
    if (percent < 80) return 'var(--color-warning)';
    return 'var(--color-danger)';
}

export function StatBar({ label, value, max, formatted }: StatBarProps) {
    const percent = max > 0 ? (value / max) * 100 : 0;
    const clampedPercent = Math.min(100, Math.max(0, percent));
    const color = getBarColor(clampedPercent);

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
            {/* Bar at the bottom — color transitions smoothly */}
            <div style={{
                height: 4,
                borderRadius: 2,
                background: 'rgba(255,255,255,0.06)',
                overflow: 'hidden',
            }}>
                <div style={{
                    height: '100%',
                    width: `${animatedWidth}%`,
                    background: color,
                    borderRadius: 2,
                    transition: 'width 500ms ease, background 500ms ease',
                    boxShadow: clampedPercent > 80 ? `0 0 8px ${color}40` : 'none',
                }} />
            </div>
        </div>
    );
}
