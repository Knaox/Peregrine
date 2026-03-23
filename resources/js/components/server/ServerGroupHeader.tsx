import type { ServerGroupHeaderProps } from '@/components/server/ServerGroupHeader.props';

export function ServerGroupHeader({ name, count, eggImage }: ServerGroupHeaderProps) {
    return (
        <div className="flex items-center gap-4 py-5">
            <div className="h-px flex-1 bg-gradient-to-r from-transparent via-[var(--color-border-hover)] to-transparent" />
            <div className="flex items-center gap-2.5">
                {eggImage && (
                    <img
                        src={eggImage}
                        alt=""
                        className="h-6 w-6 rounded-[var(--radius-sm)] object-cover ring-1 ring-[var(--color-border)]"
                    />
                )}
                <span className="text-xs uppercase tracking-[0.2em] font-semibold text-[var(--color-text-secondary)]">
                    {name}
                </span>
                <span className="flex h-5 min-w-5 items-center justify-center rounded-[var(--radius-full)] bg-[var(--color-surface-hover)] px-1.5 text-[0.65rem] font-semibold text-[var(--color-text-muted)] ring-1 ring-[var(--color-border)]">
                    {count}
                </span>
            </div>
            <div className="h-px flex-1 bg-gradient-to-r from-transparent via-[var(--color-border-hover)] to-transparent" />
        </div>
    );
}
