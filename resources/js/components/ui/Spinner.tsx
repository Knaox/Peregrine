import clsx from 'clsx';
import { type SpinnerProps } from '@/components/ui/Spinner.props';

const sizeMap: Record<NonNullable<SpinnerProps['size']>, { container: string; thickness: string }> = {
    sm: { container: 'w-4 h-4', thickness: '2px' },
    md: { container: 'w-6 h-6', thickness: '3px' },
    lg: { container: 'w-10 h-10', thickness: '3px' },
};

export function Spinner({ size = 'md', className }: SpinnerProps) {
    const { container, thickness } = sizeMap[size];

    return (
        <div className="relative inline-flex items-center justify-center">
            <div
                className={clsx('rounded-full animate-spin', container, className)}
                style={{
                    background: 'conic-gradient(from 0deg, transparent 0%, var(--color-primary) 100%)',
                    mask: `radial-gradient(farthest-side, transparent calc(100% - ${thickness}), black calc(100% - ${thickness} + 1px))`,
                    WebkitMask: `radial-gradient(farthest-side, transparent calc(100% - ${thickness}), black calc(100% - ${thickness} + 1px))`,
                    filter: 'drop-shadow(0 0 4px var(--color-primary-glow))',
                }}
                role="status"
                aria-label="Loading"
            />
        </div>
    );
}
