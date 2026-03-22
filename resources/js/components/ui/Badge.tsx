import clsx from 'clsx';
import { type BadgeProps } from '@/components/ui/Badge.props';

const colorClasses: Record<NonNullable<BadgeProps['color']>, string> = {
    green: 'bg-green-500/20 text-green-400',
    yellow: 'bg-yellow-500/20 text-yellow-400',
    red: 'bg-red-500/20 text-red-400',
    gray: 'bg-[var(--color-text-muted)]/20 text-[var(--color-text-secondary)]',
    orange: 'bg-[var(--color-primary)]/20 text-[var(--color-primary)]',
    blue: 'bg-blue-500/20 text-blue-400',
};

export function Badge({ color = 'gray', children, className }: BadgeProps) {
    return (
        <span
            className={clsx(
                'px-2 py-0.5 rounded-full text-xs font-medium inline-block',
                colorClasses[color],
                className,
            )}
        >
            {children}
        </span>
    );
}
