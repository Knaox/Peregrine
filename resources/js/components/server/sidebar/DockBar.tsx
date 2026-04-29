import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { NavLink, useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { m, AnimatePresence } from 'motion/react';
import clsx from 'clsx';
import { getIcon } from '@/utils/icons';
import { SidebarBackButton } from '@/components/server/sidebar/SidebarBackButton';
import { ServerContextPill } from '@/components/server/sidebar/ServerContextPill';
import { SidebarUserMenu } from '@/components/server/sidebar/SidebarUserMenu';
import type { useSidebarConfig } from '@/hooks/useSidebarConfig';
import type { ServerSidebarProps } from '@/components/server/ServerSidebar.props';

type DockBarProps = ServerSidebarProps & { config: ReturnType<typeof useSidebarConfig> };

const DOCK_CLEARANCE = '5.5rem';

interface HoverState {
    id: string;
    label: string;
    rect: DOMRect;
}

/**
 * Floating macOS-style dock at bottom-center.
 *
 * Discoverability for icon-only presets:
 *   - Active item morphs into an icon+label pill so the player always
 *     knows where they are.
 *   - Inactive items reveal a discreet tooltip above on hover/focus,
 *     rendered via a portal at document.body so the dock's
 *     `overflow-x-auto` (needed for many-item scroll) doesn't clip it.
 *
 * WCAG: all interactive controls ≥ 44×44px. Dock has overflow-x-auto so
 * 8+ items still scroll on narrow phones.
 */
export function DockBar({ server, config }: DockBarProps) {
    const { t } = useTranslation();
    const style = config.style ?? 'pills';
    const withLabels = style === 'default';
    const iconOnly = !withLabels;
    const location = useLocation();
    const [hover, setHover] = useState<HoverState | null>(null);

    useEffect(() => {
        document.documentElement.style.setProperty('--bottom-safe-area', DOCK_CLEARANCE);
        return () => {
            document.documentElement.style.removeProperty('--bottom-safe-area');
        };
    }, []);

    // A click that triggers a route change should dismiss the tooltip
    // immediately — `onMouseLeave` won't fire reliably after navigation.
    useEffect(() => {
        setHover(null);
    }, [location.pathname]);

    const isEntryActive = (suffix: string): boolean => {
        const target = `/servers/${server.id}${suffix}`;
        if (suffix === '') return location.pathname === target;
        return location.pathname === target || location.pathname.startsWith(target + '/');
    };

    const enter = (id: string, label: string, el: HTMLElement | null): void => {
        if (!el) return;
        setHover({ id, label, rect: el.getBoundingClientRect() });
    };
    const leave = (id: string): void => {
        setHover(prev => (prev?.id === id ? null : prev));
    };

    return (
        <>
            <div className="fixed left-3 top-3 z-40 flex items-center gap-2">
                <SidebarBackButton />
                <ServerContextPill
                    server={server}
                    showStatus={config.show_server_status !== false}
                    showName={config.show_server_name !== false}
                />
            </div>

            <div className="fixed right-3 top-3 z-40">
                <SidebarUserMenu align="bottom" />
            </div>

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
                        withLabels ? 'gap-1 px-3' : 'gap-1.5 px-3',
                    )}
                    style={{
                        borderRadius: '9999px',
                        background: 'var(--color-glass)',
                        backdropFilter: 'blur(calc(var(--sidebar-blur-intensity, 12px) + 6px)) saturate(180%)',
                        border: '1px solid var(--color-glass-border)',
                        boxShadow: 'var(--shadow-lg), var(--glass-highlight)',
                    }}
                >
                    {config.entries.map((entry, i) => {
                        const label = t(entry.label_key);
                        const active = isEntryActive(entry.route_suffix);
                        const showInlineLabel = withLabels || (iconOnly && active);
                        const wantTooltip = iconOnly && !active;
                        return (
                            <DockItem
                                key={entry.id}
                                id={entry.id}
                                label={label}
                                index={i}
                                wantTooltip={wantTooltip}
                                onEnter={enter}
                                onLeave={leave}
                                to={`/servers/${server.id}${entry.route_suffix}`}
                                end={entry.route_suffix === ''}
                                ariaLabel={label}
                                className={({ isActive }) => clsx(
                                    'flex items-center transition-[background,color,padding,box-shadow,transform] duration-200 ease-out min-h-[44px]',
                                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-ring)] focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--color-background)]',
                                    showInlineLabel
                                        ? 'gap-2 px-3.5 py-2 text-sm font-medium whitespace-nowrap'
                                        : 'justify-center p-3 min-w-[44px] hover:scale-110 active:scale-95',
                                    isActive && !withLabels && 'scale-[1.04]',
                                )}
                                style={({ isActive }) => getDockItemStyle(style, isActive)}
                            >
                                {getIcon(entry.icon)}
                                {showInlineLabel ? (
                                    <m.span
                                        key="lbl"
                                        initial={iconOnly ? { opacity: 0, width: 0 } : false}
                                        animate={{ opacity: 1, width: 'auto' }}
                                        transition={{ duration: 0.18, ease: [0.4, 0, 0.2, 1] }}
                                        className="overflow-hidden"
                                    >
                                        {label}
                                    </m.span>
                                ) : null}
                            </DockItem>
                        );
                    })}
                </nav>
            </m.div>

            <FloatingTooltip hover={hover} />
        </>
    );
}

interface DockItemProps {
    id: string;
    label: string;
    index: number;
    wantTooltip: boolean;
    onEnter: (id: string, label: string, el: HTMLElement | null) => void;
    onLeave: (id: string) => void;
    to: string;
    end: boolean;
    ariaLabel: string;
    className: string | (({ isActive }: { isActive: boolean }) => string);
    style: React.CSSProperties | (({ isActive }: { isActive: boolean }) => React.CSSProperties);
    children: React.ReactNode;
}

/**
 * Wraps a NavLink with a motion entrance + tooltip hover/focus tracking.
 * The ref points directly to the rendered `<a>` so the bounding rect we use
 * to position the tooltip matches the visible icon precisely (no wrapper
 * offsets or motion transforms in the way).
 */
function DockItem({
    id, label, index, wantTooltip, onEnter, onLeave,
    to, end, ariaLabel, className, style, children,
}: DockItemProps) {
    const linkRef = useRef<HTMLAnchorElement>(null);
    return (
        <m.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: index * 0.03, duration: 0.25 }}
            onMouseEnter={() => wantTooltip && onEnter(id, label, linkRef.current)}
            onMouseLeave={() => onLeave(id)}
            onFocus={() => wantTooltip && onEnter(id, label, linkRef.current)}
            onBlur={() => onLeave(id)}
            onClick={() => onLeave(id)}
        >
            <NavLink
                ref={linkRef}
                to={to}
                end={end}
                aria-label={ariaLabel}
                className={className}
                style={style}
            >
                {children}
            </NavLink>
        </m.div>
    );
}

/**
 * Tooltip rendered via React portal at document.body so it can never be
 * clipped by the dock's `overflow-x-auto`. Position is computed from the
 * hovered item's bounding rect and follows window scroll/resize implicitly
 * because the dock itself is `position: fixed` (rect coords stay stable).
 */
function FloatingTooltip({ hover }: { hover: HoverState | null }) {
    if (typeof document === 'undefined') return null;
    return createPortal(
        <AnimatePresence>
            {hover ? (
                <m.span
                    key={hover.id}
                    role="tooltip"
                    initial={{ opacity: 0, y: 4 }}
                    animate={{ opacity: 1, y: 0 }}
                    exit={{ opacity: 0, y: 4 }}
                    transition={{ duration: 0.16, ease: [0.4, 0, 0.2, 1] }}
                    className="pointer-events-none fixed whitespace-nowrap rounded-md px-2 py-1 text-[11px] font-medium z-[1000]"
                    style={{
                        top: hover.rect.top - 12,
                        left: hover.rect.left + hover.rect.width / 2,
                        transform: 'translate(-50%, -100%)',
                        background: 'var(--color-surface-elevated, var(--color-surface))',
                        color: 'var(--color-text-primary)',
                        border: '1px solid var(--color-border)',
                        boxShadow: 'var(--shadow-md, 0 6px 24px rgba(0,0,0,0.18))',
                        backdropFilter: 'blur(8px)',
                    }}
                >
                    {hover.label}
                </m.span>
            ) : null}
        </AnimatePresence>,
        document.body,
    );
}

function getDockItemStyle(style: string, isActive: boolean): React.CSSProperties {
    if (style === 'compact') {
        return isActive
            ? { borderRadius: 'var(--radius)', color: 'var(--color-primary)', borderBottom: '2px solid var(--color-primary)' }
            : { borderRadius: 'var(--radius)', color: 'var(--color-text-secondary)', borderBottom: '2px solid transparent' };
    }
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
