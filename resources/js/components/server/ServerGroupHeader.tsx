import { m } from 'motion/react';
import type { ServerGroupHeaderProps } from '@/components/server/ServerGroupHeader.props';

export function ServerGroupHeader({ name, count, eggImage }: ServerGroupHeaderProps) {
    return (
        <m.div
            initial={{ opacity: 0, scaleX: 0.5 }}
            animate={{ opacity: 1, scaleX: 1 }}
            transition={{ duration: 0.4 }}
            className="flex items-center gap-4 py-5"
        >
            <div className="h-px flex-1 bg-gradient-to-r from-transparent via-[var(--color-border-hover)] to-transparent" />
            <div className="flex items-center gap-2.5">
                {eggImage && (
                    <img
                        src={eggImage}
                        alt=""
                        className="h-6 w-6 rounded-[var(--radius-sm)] object-cover ring-1 ring-[var(--color-border)] shadow-[0_0_8px_rgba(0,0,0,0.3)]"
                    />
                )}
                <span className="text-xs uppercase tracking-[0.2em] font-semibold text-[var(--color-text-secondary)]">
                    {name}
                </span>
                <span className="flex h-5 min-w-5 items-center justify-center rounded-[var(--radius-full)] px-1.5 text-[0.65rem] font-semibold text-[var(--color-text-muted)]"
                    style={{ background: 'rgba(var(--color-primary-rgb), 0.1)', color: 'var(--color-primary)', border: '1px solid rgba(var(--color-primary-rgb), 0.2)' }}>
                    {count}
                </span>
            </div>
            <div className="h-px flex-1 bg-gradient-to-r from-transparent via-[var(--color-border-hover)] to-transparent" />
        </m.div>
    );
}
