import { useEffect } from 'react';
import { NavLink } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m } from 'motion/react';
import clsx from 'clsx';
import { getIcon } from '@/utils/icons';
import { SidebarBackButton } from '@/components/server/sidebar/SidebarBackButton';
import { ServerContextPill } from '@/components/server/sidebar/ServerContextPill';
import { SidebarUserMenu } from '@/components/server/sidebar/SidebarUserMenu';
import type { useSidebarConfig } from '@/hooks/useSidebarConfig';
import type { ServerSidebarProps } from '@/components/server/ServerSidebar.props';

type DockBarProps = ServerSidebarProps & { config: ReturnType<typeof useSidebarConfig> };

/**
 * Vertical clearance the dock occupies (button height + bottom-4 + breathing
 * room). Floating UI like bulk action bars reads `--bottom-safe-area` so it
 * never sits underneath the dock — see ServerBulkBar / FileBulkBar.
 */
const DOCK_CLEARANCE = '5.5rem';

/**
 * Floating macOS-style dock at bottom-center. The hero banner is fully
 * visible because the dock floats over it rather than sharing layout.
 *
 * The `style` preset option drives the item appearance:
 *   - 'default' → icon + label (labeled dock, wider)
 *   - 'compact' → icon only, subtle active underline (minimal dock)
 *   - 'pills'   → icon only, rounded pill active state (classic macOS)
 *
 * WCAG: all interactive controls ≥ 44×44px. Dock has overflow-x-auto so
 * 8+ items still scroll on narrow phones.
 */
export function DockBar({ server, config }: DockBarProps) {
    const { t } = useTranslation();
    const style = config.style ?? 'pills';
    const withLabels = style === 'default';

    // Publish the dock clearance on the document so floating UI (bulk bars,
    // floating selection toolbars) can lift themselves above the dock. Other
    // sidebar variants don't set the var → it falls back to 0.
    useEffect(() => {
        document.documentElement.style.setProperty('--bottom-safe-area', DOCK_CLEARANCE);
        return () => {
            document.documentElement.style.removeProperty('--bottom-safe-area');
        };
    }, []);

    return (
        <>
            {/* Top-left fixed context — back + server identity. */}
            <div className="fixed left-3 top-3 z-40 flex items-center gap-2">
                <SidebarBackButton />
                <ServerContextPill
                    server={server}
                    showStatus={config.show_server_status !== false}
                    showName={config.show_server_name !== false}
                />
            </div>

            {/* Top-right fixed user menu — compact avatar with popover. */}
            <div className="fixed right-3 top-3 z-40">
                <SidebarUserMenu align="bottom" />
            </div>

            {/* Floating dock pinned to bottom-center. */}
            <m.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3, ease: [0.4, 0, 0.2, 1] }}
                className="fixed inset-x-0 bottom-4 z-40 flex justify-center pointer-events-none px-3"
            >
                <nav
                    role="navigation"
                    aria-label={t('servers.sidebar.principal')}
                    className={clsx(
                        'pointer-events-auto flex items-center overflow-x-auto py-2 max-w-[calc(100vw-24px)]',
                        withLabels ? 'gap-1 px-3' : 'gap-2 px-3',
                    )}
                    style={{
                        borderRadius: '9999px',
                        background: 'var(--color-glass)',
                        backdropFilter: 'blur(18px) saturate(180%)',
                        border: '1px solid var(--color-glass-border)',
                        boxShadow: 'var(--shadow-lg), var(--glass-highlight)',
                    }}
                >
                    {config.entries.map((entry, i) => (
                        <m.div
                            key={entry.id}
                            initial={{ opacity: 0, y: 10 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ delay: i * 0.03, duration: 0.25 }}
                        >
                            <NavLink
                                to={`/servers/${server.id}${entry.route_suffix}`}
                                end={entry.route_suffix === ''}
                                title={t(entry.label_key)}
                                aria-label={t(entry.label_key)}
                                className={({ isActive }) => clsx(
                                    'flex items-center transition-all duration-200 min-h-[44px]',
                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-background)]',
                                    withLabels
                                        ? 'gap-2 px-3.5 py-2 text-sm font-medium whitespace-nowrap hover:scale-[1.03]'
                                        : 'justify-center p-3 min-w-[44px] hover:scale-110',
                                    isActive && (withLabels ? 'scale-[1.02]' : 'scale-110'),
                                )}
                                style={({ isActive }) => getDockItemStyle(style, isActive)}
                            >
                                {getIcon(entry.icon)}
                                {withLabels && <span>{t(entry.label_key)}</span>}
                            </NavLink>
                        </m.div>
                    ))}
                </nav>
            </m.div>
        </>
    );
}

function getDockItemStyle(style: string, isActive: boolean): React.CSSProperties {
    // 'compact' = subtle underline on active, no filled background
    if (style === 'compact') {
        return isActive
            ? { borderRadius: 'var(--radius)', color: 'var(--color-primary)', borderBottom: '2px solid var(--color-primary)' }
            : { borderRadius: 'var(--radius)', color: 'var(--color-text-secondary)', borderBottom: '2px solid transparent' };
    }
    // 'default' (with labels) = pill background around icon+text
    // 'pills' (icon-only) = fully rounded pill — macOS dock feel
    const radius = '9999px';
    return isActive
        ? {
            borderRadius: radius,
            background: 'rgba(var(--color-primary-rgb), 0.18)',
            color: 'var(--color-primary)',
            boxShadow: '0 0 18px rgba(var(--color-primary-rgb), 0.35)',
        }
        : { borderRadius: radius, color: 'var(--color-text-secondary)' };
}
