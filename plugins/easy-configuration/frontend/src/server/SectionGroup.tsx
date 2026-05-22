import clsx from 'clsx';
import { ChevronDown } from 'lucide-react';
import { useState, type ReactNode } from 'react';

/**
 * Collapsible section block. Open by default; the collapsed state is persisted
 * in localStorage per (server, file, section) so a player's layout sticks.
 */
export function SectionGroup({
    title,
    storageKey,
    count,
    children,
}: {
    title: ReactNode;
    storageKey: string;
    count?: number;
    children: ReactNode;
}) {
    const [open, setOpen] = useState<boolean>(() => {
        try {
            return localStorage.getItem(storageKey) !== '0';
        } catch {
            return true;
        }
    });

    const toggle = (): void => {
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
        <div className={clsx('ec-section-group', !open && 'ec-section-collapsed')}>
            <button type="button" className="ec-section-head" onClick={toggle} aria-expanded={open}>
                <span className="ec-section-chevron">
                    <ChevronDown size={16} />
                </span>
                <span>{title}</span>
                {count !== undefined && <span className="ec-section-count">{count}</span>}
            </button>
            {open && <div className="ec-section-body">{children}</div>}
        </div>
    );
}
