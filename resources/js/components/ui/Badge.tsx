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
        badge: 'bg-[var(--color-success)]/10 text-[var(--color-success)] border border-[var(--color-success)]/20',
        dot: 'bg-[var(--color-success)]',
        glow: 'shadow-[0_0_6px_var(--color-success-glow)]',
        pulse: true,
    },
    yellow: {
        badge: 'bg-[var(--color-warning)]/10 text-[var(--color-warning)] border border-[var(--color-warning)]/20',
        dot: 'bg-[var(--color-warning)]',
        glow: 'shadow-[0_0_6px_rgba(var(--color-warning-rgb),0.5)]',
        pulse: false,
    },
    red: {
        badge: 'bg-[var(--color-danger)]/10 text-[var(--color-danger)] border border-[var(--color-danger)]/20',
        dot: 'bg-[var(--color-danger)]',
        glow: 'shadow-[0_0_6px_var(--color-danger-glow)]',
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
        badge: 'bg-[var(--color-info)]/10 text-[var(--color-info)] border border-[var(--color-info)]/20',
        dot: 'bg-[var(--color-info)]',
        glow: 'shadow-[0_0_6px_rgba(var(--color-info-rgb),0.5)]',
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
