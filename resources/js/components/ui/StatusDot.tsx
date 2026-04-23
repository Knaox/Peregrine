import clsx from 'clsx';
import { type StatusDotProps } from '@/components/ui/StatusDot.props';

const colorMap: Record<StatusDotProps['status'], string> = {
    running: 'bg-[var(--color-success)]',
    active: 'bg-[var(--color-success)]',
    starting: 'bg-[var(--color-primary)]',
    stopped: 'bg-[var(--color-text-muted)]',
    offline: 'bg-[var(--color-danger)]',
    suspended: 'bg-[var(--color-warning)]',
    terminated: 'bg-[var(--color-danger)]',
    provisioning: 'bg-[var(--color-primary)]',
    provisioning_failed: 'bg-[var(--color-danger)]',
};

const glowMap: Record<StatusDotProps['status'], string> = {
    running: 'shadow-[0_0_6px_var(--color-success-glow)]',
    active: 'shadow-[0_0_6px_var(--color-success-glow)]',
    starting: 'shadow-[0_0_6px_var(--color-primary-glow)]',
    stopped: '',
    offline: 'shadow-[0_0_6px_var(--color-danger-glow)]',
    suspended: 'shadow-[0_0_6px_rgba(245,158,11,0.3)]',
    terminated: 'shadow-[0_0_6px_var(--color-danger-glow)]',
    provisioning: 'shadow-[0_0_6px_var(--color-primary-glow)]',
    provisioning_failed: 'shadow-[0_0_6px_var(--color-danger-glow)]',
};

const sizeMap: Record<NonNullable<StatusDotProps['size']>, string> = {
    sm: 'h-2 w-2',
    md: 'h-2.5 w-2.5',
};

const defaultPulseStatuses = new Set<StatusDotProps['status']>([
    'running',
    'active',
    'starting',
    'provisioning',
]);

export function StatusDot({ status, size = 'sm', pulse }: StatusDotProps) {
    const shouldPulse = pulse ?? defaultPulseStatuses.has(status);

    return (
        <span className='relative inline-flex'>
            {shouldPulse && (
                <span
                    className={clsx(
                        'absolute inline-flex h-full w-full rounded-full opacity-40',
                        'animate-ping',
                        colorMap[status],
                    )}
                />
            )}
            <span
                className={clsx(
                    'relative inline-flex rounded-full',
                    colorMap[status],
                    glowMap[status],
                    sizeMap[size],
                )}
            />
        </span>
    );
}
