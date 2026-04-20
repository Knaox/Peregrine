import { useTranslation } from 'react-i18next';
import { NavLinks } from '@/components/server/sidebar/NavLinks';
import { SidebarBackButton } from '@/components/server/sidebar/SidebarBackButton';
import { ServerContextPill } from '@/components/server/sidebar/ServerContextPill';
import { SidebarUserMenu } from '@/components/server/sidebar/SidebarUserMenu';
import type { useSidebarConfig } from '@/hooks/useSidebarConfig';
import type { ServerSidebarProps } from '@/components/server/ServerSidebar.props';

type TopTabsBarProps = ServerSidebarProps & { config: ReturnType<typeof useSidebarConfig> };

/**
 * Horizontal top layout — restructured into two rows so the user doesn't
 * lose access to Back / server identity / logout (those were missing in
 * the previous single-row version).
 *
 * Row 1: Back · server context · user menu.
 * Row 2: Scrollable nav tabs.
 */
export function TopTabsBar({ server, config }: TopTabsBarProps) {
    const { t } = useTranslation();

    return (
        <div
            className="flex flex-col flex-shrink-0"
            style={{
                background: 'var(--color-glass)',
                backdropFilter: 'blur(14px) saturate(180%)',
                borderBottom: '1px solid var(--color-border)',
            }}
        >
            {/* Row 1 — context + back + user */}
            <div
                className="flex items-center gap-3 px-3 sm:px-4 py-2"
                style={{ borderBottom: '1px solid var(--color-border)' }}
            >
                <SidebarBackButton />
                <ServerContextPill
                    server={server}
                    showStatus={config.show_server_status !== false}
                    showName={config.show_server_name !== false}
                />
                <div className="ml-auto flex items-center gap-2">
                    <SidebarUserMenu align="bottom" />
                </div>
            </div>

            {/* Row 2 — navigation tabs */}
            <nav
                role="navigation"
                aria-label={t('servers.sidebar.principal')}
                className="flex items-center gap-2 overflow-x-auto px-3 sm:px-4"
            >
                <NavLinks entries={config.entries} serverId={server.id} style={config.style} isTop />
            </nav>
        </div>
    );
}
