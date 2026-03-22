import type { ServerGroupHeaderProps } from '@/components/server/ServerGroupHeader.props';

export function ServerGroupHeader({ name, count }: ServerGroupHeaderProps) {
    return (
        <div className="flex items-center gap-3 py-4">
            <div className="h-px flex-1 bg-[var(--color-border)]" />
            <span className="text-xs font-medium uppercase tracking-widest text-[var(--color-text-muted)]">
                {name} ({count})
            </span>
            <div className="h-px flex-1 bg-[var(--color-border)]" />
        </div>
    );
}
