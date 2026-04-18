import clsx from 'clsx';
import { type CardProps } from '@/components/ui/Card.props';

export function Card({
    hover = false,
    glass = false,
    glow = false,
    className,
    children,
    onClick,
}: CardProps) {
    return (
        <div
            className={clsx(
                'rounded-[var(--radius-lg)]',
                'transition-all duration-300',
                glass
                    ? 'glass-card-enhanced'
                    : 'bg-[var(--color-surface)] border border-[var(--color-border)] shadow-[var(--shadow-sm)]',
                hover && [
                    'hover:border-[var(--color-border-hover)]',
                    'hover:shadow-[var(--shadow-md)]',
                    'hover:-translate-y-0.5',
                    'cursor-pointer',
                ],
                glow && 'hover:shadow-[0_0_24px_var(--color-primary-glow)]',
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
