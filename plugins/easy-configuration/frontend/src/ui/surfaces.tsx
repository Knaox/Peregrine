import clsx from 'clsx';
import type { ReactNode } from 'react';

export function Spinner({ size }: { size?: 'sm' | 'lg' }) {
    return <span className={clsx('ec-spinner', size === 'lg' && 'ec-spinner-lg')} aria-hidden />;
}

export function Card({ children, className, hover }: { children: ReactNode; className?: string; hover?: boolean }) {
    return <div className={clsx('ec-card', hover && 'ec-card-hover', className)}>{children}</div>;
}

type BadgeVariant = 'accent' | 'info' | 'warning' | 'success' | 'muted';

export function Badge({ variant = 'muted', children }: { variant?: BadgeVariant; children: ReactNode }) {
    return <span className={clsx('ec-badge', `ec-badge-${variant}`)}>{children}</span>;
}

export function EmptyState({ children }: { children: ReactNode }) {
    return <div className="ec-empty">{children}</div>;
}

export interface TabItem {
    id: string;
    label: string;
}

export function Tabs({ tabs, active, onChange }: { tabs: TabItem[]; active: string; onChange: (id: string) => void }) {
    return (
        <div className="ec-tabs" role="tablist">
            {tabs.map((tab) => (
                <button
                    key={tab.id}
                    type="button"
                    role="tab"
                    aria-selected={tab.id === active}
                    className={clsx('ec-tab', tab.id === active && 'ec-tab-active')}
                    onClick={() => onChange(tab.id)}
                >
                    {tab.label}
                </button>
            ))}
        </div>
    );
}

/**
 * `align` controls which edge of the trigger the popup anchors to. Default
 * `center`. Use `start` for triggers that sit at the left edge of a narrow
 * container (e.g. the boost controls in a multi-column card) so the popup
 * extends rightward and never spills past the container's left edge — where an
 * ancestor `overflow: hidden` would otherwise clip it.
 */
export function Tooltip({
    content,
    children,
    align = 'center',
}: {
    content: string;
    children: ReactNode;
    align?: 'center' | 'start' | 'end';
}) {
    return (
        <span className="ec-tooltip" tabIndex={0}>
            {children}
            <span
                role="tooltip"
                className={clsx('ec-tooltip-pop', align === 'start' && 'ec-tooltip-pop-start', align === 'end' && 'ec-tooltip-pop-end')}
            >
                {content}
            </span>
        </span>
    );
}
