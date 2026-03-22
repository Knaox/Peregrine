import { useState, useEffect } from 'react';
import clsx from 'clsx';
import { type StatBarProps } from '@/components/ui/StatBar.props';

function resolveColor(percent: number): NonNullable<StatBarProps['color']> {
    if (percent > 85) return 'red';
    if (percent >= 60) return 'yellow';
    return 'green';
}

const barGradientClasses: Record<NonNullable<StatBarProps['color']>, string> = {
    green: 'bg-gradient-to-r from-green-600 to-green-400',
    yellow: 'bg-gradient-to-r from-yellow-600 to-yellow-400',
    red: 'bg-gradient-to-r from-red-600 to-red-400',
    orange: 'bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-primary-hover)]',
};

const barGlowClasses: Record<NonNullable<StatBarProps['color']>, string> = {
    green: 'shadow-[0_0_8px_var(--color-success-glow)]',
    yellow: 'shadow-[0_0_8px_rgba(245,158,11,0.2)]',
    red: 'shadow-[0_0_8px_var(--color-danger-glow)]',
    orange: 'shadow-[0_0_8px_var(--color-primary-glow)]',
};

export function StatBar({ label, value, max, formatted, color }: StatBarProps) {
    const percent = max > 0 ? (value / max) * 100 : 0;
    const clampedPercent = Math.min(100, Math.max(0, percent));
    const resolvedColor = color ?? resolveColor(clampedPercent);

    const [animatedWidth, setAnimatedWidth] = useState(0);

    useEffect(() => {
        const timer = setTimeout(() => {
            setAnimatedWidth(clampedPercent);
        }, 100);
        return () => clearTimeout(timer);
    }, [clampedPercent]);

    return (
        <div className='flex flex-col gap-1.5'>
            <div className='flex items-center justify-between'>
                <span className='text-sm text-[var(--color-text-secondary)]'>
                    {label}
                </span>
                <span className='text-sm font-medium text-[var(--color-text-primary)]'>
                    {formatted}
                </span>
            </div>
            <div className='h-2 w-full rounded-full bg-[var(--color-surface)] overflow-hidden'>
                <div
                    className={clsx(
                        'h-2 rounded-full',
                        'transition-all duration-700 ease-out',
                        barGradientClasses[resolvedColor],
                        barGlowClasses[resolvedColor],
                    )}
                    style={{ width: `${animatedWidth}%` }}
                />
            </div>
        </div>
    );
}
