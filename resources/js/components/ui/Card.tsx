import clsx from 'clsx';
import { type CardProps } from '@/components/ui/Card.props';

export function Card({ hover = false, className, children, onClick }: CardProps) {
    return (
        <div
            className={clsx(
                'bg-slate-800 border border-slate-700 rounded-xl',
                hover && 'hover:border-slate-600 transition-colors cursor-pointer',
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
