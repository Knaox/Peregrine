import { NavLink } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import { getIcon } from '@/utils/icons';
import type { SidebarEntry } from '@/hooks/useSidebarConfig';

interface NavLinksProps {
    entries: SidebarEntry[];
    serverId: number;
    /** 'default' | 'compact' | 'pills' */
    style: string;
    isTop: boolean;
    onNavClick?: () => void;
}

function getActiveStyle(style: string, isActive: boolean, isTop: boolean): React.CSSProperties {
    if (isTop) {
        return isActive
            ? { borderBottom: '2px solid var(--color-primary)', color: 'var(--color-primary)', background: 'transparent' }
            : { borderBottom: '2px solid transparent', color: 'var(--color-text-secondary)' };
    }
    if (style === 'compact') {
        return isActive
            ? { borderRadius: 'var(--radius)', background: 'rgba(var(--color-primary-rgb), 0.15)', color: 'var(--color-primary)', boxShadow: '0 0 14px rgba(var(--color-primary-rgb), 0.2)' }
            : { borderRadius: 'var(--radius)', color: 'var(--color-text-secondary)' };
    }
    if (style === 'pills') {
        return isActive
            ? { borderRadius: '9999px', background: 'rgba(var(--color-primary-rgb), 0.15)', color: 'var(--color-primary)', boxShadow: '0 0 12px rgba(var(--color-primary-rgb), 0.1)' }
            : { borderRadius: '9999px', color: 'var(--color-text-secondary)' };
    }
    return isActive
        ? { borderLeft: '3px solid var(--color-primary)', background: 'rgba(var(--color-primary-rgb), 0.08)', boxShadow: 'inset 3px 0 12px -4px rgba(var(--color-primary-rgb), 0.3)', color: 'var(--color-primary)', borderRadius: '0 var(--radius) var(--radius) 0' }
        : { borderLeft: '3px solid transparent', color: 'var(--color-text-secondary)', borderRadius: '0 var(--radius) var(--radius) 0' };
}

/**
 * Shared navigation list for all 4 sidebar presets.
 *
 * WCAG compliant:
 * - Min 44×44px touch target (p-3 + icon).
 * - react-router NavLink auto-sets aria-current="page" on active link.
 * - focus-visible ring on keyboard navigation.
 * - Rail layout provides title + aria-label (native tooltip for collapsed icons).
 */
export function NavLinks({ entries, serverId, style, isTop, onNavClick }: NavLinksProps) {
    const { t } = useTranslation();
    const isRail = style === 'compact' && !isTop;

    return (
        <>
            {entries.map((entry, i) => (
                <m.div
                    key={entry.id}
                    initial={{ opacity: 0, x: isTop ? 0 : -10 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: i * 0.04, duration: 0.25 }}
                >
                    <NavLink
                        to={`/servers/${serverId}${entry.route_suffix}`}
                        end={entry.route_suffix === ''}
                        onClick={onNavClick}
                        title={isRail ? t(entry.label_key) : undefined}
                        aria-label={isRail ? t(entry.label_key) : undefined}
                        className={({ isActive }) => clsx(
                            'flex items-center text-sm font-medium transition-all duration-150',
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-background)]',
                            // 44×44 minimum touch target across every layout.
                            isRail && 'justify-center p-3 min-h-[44px] min-w-[44px]',
                            !isRail && isTop && 'gap-2.5 px-4 py-3 min-h-[44px] whitespace-nowrap',
                            !isRail && !isTop && 'gap-2.5 px-3 py-3 min-h-[44px]',
                            !isActive && 'hover:bg-[var(--surface-overlay-hover)]',
                        )}
                        style={({ isActive }) => getActiveStyle(style, isActive, isTop)}
                    >
                        {getIcon(entry.icon)}
                        {!isRail && t(entry.label_key)}
                    </NavLink>
                </m.div>
            ))}
        </>
    );
}
