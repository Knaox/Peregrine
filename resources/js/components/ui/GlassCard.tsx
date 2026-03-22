import clsx from 'clsx';
import { type GlassCardProps } from '@/components/ui/GlassCard.props';

export function GlassCard({
    children,
    className,
    hover = false,
    glow = false,
    onClick,
}: GlassCardProps) {
    return (
        <div
            className={clsx(
                'rounded-[var(--radius-lg)]',
                'backdrop-blur-xl bg-[var(--color-glass)]',
                'border border-[var(--color-glass-border)]',
                'shadow-[var(--shadow-md)]',
                'transition-all duration-[var(--transition-smooth)]',
                hover && [
                    'hover:border-[var(--color-border-hover)]',
                    'hover:shadow-[var(--shadow-lg)]',
                    'cursor-pointer',
                ],
                glow && 'hover:shadow-[var(--shadow-glow)]',
                onClick && !hover && 'cursor-pointer',
                className,
            )}
            onClick={onClick}
            role={onClick ? 'button' : undefined}
            tabIndex={onClick ? 0 : undefined}
            onKeyDown={
                onClick
                    ? (e) => {
                          if (e.key === 'Enter' || e.key === ' ') {
                              e.preventDefault();
                              onClick();
                          }
                      }
                    : undefined
            }
        >
            {children}
        </div>
    );
}
