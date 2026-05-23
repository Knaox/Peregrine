import clsx from 'clsx';
import { ChevronDown } from 'lucide-react';
import { useState, type ReactNode } from 'react';

/**
 * Collapsible section block. Open by default; the collapsed state is persisted
 * in localStorage per (server, file, section) so a player's layout sticks.
 * `forceCollapsed` (server running) overrides the UI to closed and locks the
 * toggle WITHOUT touching localStorage — so the player's own layout is restored
 * unchanged once the server is offline again.
 */
/** Body class for the chosen column layout (1 = list, 2/3 = responsive grid). */
export function sectionBodyClass(columns?: number): string {
    return clsx('ec-section-body', columns === 2 && 'ec-section-cols-2', columns === 3 && 'ec-section-cols-3');
}

export function SectionGroup({
    title,
    storageKey,
    count,
    children,
    forceCollapsed = false,
    columns,
}: {
    title: ReactNode;
    storageKey: string;
    count?: number;
    children: ReactNode;
    forceCollapsed?: boolean;
    columns?: number;
}) {
    const [open, setOpen] = useState<boolean>(() => {
        try {
            return localStorage.getItem(storageKey) !== '0';
        } catch {
            return true;
        }
    });

    const isOpen = forceCollapsed ? false : open;

    const toggle = (): void => {
        if (forceCollapsed) {
            return;
        }
        setOpen((current) => {
            const next = !current;
            try {
                localStorage.setItem(storageKey, next ? '1' : '0');
            } catch {
                /* localStorage unavailable — keep in-memory only */
            }

            return next;
        });
    };

    return (
        <div className={clsx('ec-section-group', !isOpen && 'ec-section-collapsed')}>
            <button type="button" className="ec-section-head" onClick={toggle} aria-expanded={isOpen} disabled={forceCollapsed}>
                <span className="ec-section-chevron">
                    <ChevronDown size={16} />
                </span>
                <span>{title}</span>
                {count !== undefined && <span className="ec-section-count">{count}</span>}
            </button>
            {isOpen && <div className={sectionBodyClass(columns)}>{children}</div>}
        </div>
    );
}
