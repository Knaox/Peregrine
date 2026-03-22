import clsx from 'clsx';
import { type BadgeProps } from '@/components/ui/Badge.props';

interface ColorConfig {
    badge: string;
    dot: string;
    glow: string;
    pulse: boolean;
}

const colorConfig: Record<NonNullable<BadgeProps['color']>, ColorConfig> = {
    green: {
        badge: 'bg-green-500/10 text-green-300 border border-green-500/20',
        dot: 'bg-green-400',
        glow: 'shadow-[0_0_6px_rgba(74,222,128,0.5)]',
        pulse: true,
    },
    yellow: {
        badge: 'bg-yellow-500/10 text-yellow-300 border border-yellow-500/20',
        dot: 'bg-yellow-400',
        glow: 'shadow-[0_0_6px_rgba(250,204,21,0.5)]',
        pulse: false,
    },
    red: {
        badge: 'bg-red-500/10 text-red-300 border border-red-500/20',
        dot: 'bg-red-400',
        glow: 'shadow-[0_0_6px_rgba(248,113,113,0.5)]',
        pulse: false,
    },
    gray: {
        badge: clsx(
            'bg-[var(--color-text-muted)]/10 text-[var(--color-text-secondary)]',
            'border border-[var(--color-text-muted)]/20',
        ),
        dot: 'bg-[var(--color-text-muted)]',
        glow: '',
        pulse: false,
    },
    orange: {
        badge: clsx(
            'bg-[var(--color-primary)]/10 text-[var(--color-primary-hover)]',
            'border border-[var(--color-primary)]/20',
        ),
        dot: 'bg-[var(--color-primary)]',
        glow: 'shadow-[0_0_6px_var(--color-primary-glow)]',
        pulse: false,
    },
    blue: {
        badge: 'bg-blue-500/10 text-blue-300 border border-blue-500/20',
        dot: 'bg-blue-400',
        glow: 'shadow-[0_0_6px_rgba(96,165,250,0.5)]',
        pulse: false,
    },
};

export function Badge({ color = 'gray', children, className }: BadgeProps) {
    const config = colorConfig[color];

    return (
        <span
            className={clsx(
                'px-2.5 py-0.5 rounded-[var(--radius-full)] text-xs font-medium',
                'inline-flex items-center gap-1.5',
                'transition-all duration-[var(--transition-fast)]',
                config.badge,
                className,
            )}
        >
            <span
                className={clsx(
                    'w-1 h-1 rounded-full flex-shrink-0',
                    config.dot,
                    config.glow,
                    config.pulse && 'animate-[pulse-glow_2s_ease-in-out_infinite]',
                )}
            />
            {children}
        </span>
    );
}
