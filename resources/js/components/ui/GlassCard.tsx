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
                'rounded-[var(--radius-lg)] glass-card-enhanced',
                'transition-all duration-300',
                hover && [
                    'hover:border-[var(--color-border-hover)]',
                    'hover:shadow-[0_8px_32px_rgba(0,0,0,0.3)]',
                    'hover:-translate-y-0.5',
                    'cursor-pointer',
                ],
                glow && 'hover:shadow-[0_0_24px_var(--color-primary-glow),0_8px_32px_rgba(0,0,0,0.3)]',
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
