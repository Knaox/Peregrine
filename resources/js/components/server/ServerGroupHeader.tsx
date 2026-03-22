import type { ServerGroupHeaderProps } from '@/components/server/ServerGroupHeader.props';

export function ServerGroupHeader({ name, count }: ServerGroupHeaderProps) {
    return (
        <div className="flex items-center gap-4 py-4">
            <div className="h-px flex-1 bg-gradient-to-r from-transparent via-[var(--color-border-hover)] to-transparent" />
            <span className="text-xs uppercase tracking-[0.2em] font-medium text-[var(--color-text-muted)]">
                {name}{' '}
                <span className="text-[var(--color-text-secondary)]">({count})</span>
            </span>
            <div className="h-px flex-1 bg-gradient-to-r from-transparent via-[var(--color-border-hover)] to-transparent" />
        </div>
    );
}
