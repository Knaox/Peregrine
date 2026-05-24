import clsx from 'clsx';
import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useCollapsed } from './useCollapsed';

/**
 * Collapsible section block. The initial state comes from the template
 * (`expandedByDefault`, base collapsed); the player's manual toggle is persisted
 * in localStorage per (server, file, section). The layout is the same whether
 * the server runs or not — running only makes the editor read-only.
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
    expandedByDefault = false,
    columns,
}: {
    title: ReactNode;
    storageKey: string;
    count?: number;
    children: ReactNode;
    expandedByDefault?: boolean;
    columns?: number;
}) {
    const { isOpen, toggle } = useCollapsed(storageKey, expandedByDefault);

    return (
        <div className={clsx('ec-section-group', !isOpen && 'ec-section-collapsed')}>
            <button type="button" className="ec-section-head" onClick={toggle} aria-expanded={isOpen}>
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
