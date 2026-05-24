import clsx from 'clsx';
import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useCollapsed } from './useCollapsed';

/**
 * Collapsible section block. The initial state comes from the template
 * (`expandedByDefault`, base collapsed); the player's manual toggle is persisted
 * in localStorage per (server, file, section). `forceCollapsed` (server running)
 * overrides the UI to closed and locks the toggle without touching localStorage,
 * so the player's own layout is restored once the server is offline again.
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
    expandedByDefault = false,
    columns,
}: {
    title: ReactNode;
    storageKey: string;
    count?: number;
    children: ReactNode;
    forceCollapsed?: boolean;
    expandedByDefault?: boolean;
    columns?: number;
}) {
    const { isOpen, toggle } = useCollapsed(storageKey, expandedByDefault, forceCollapsed);

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
