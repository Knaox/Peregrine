import { useSidebarConfig } from '@/hooks/useSidebarConfig';
import { LeftSidebar } from '@/components/server/sidebar/LeftSidebar';
import { TopTabsBar } from '@/components/server/sidebar/TopTabsBar';
import { DockBar } from '@/components/server/sidebar/DockBar';
import type { ServerSidebarProps } from '@/components/server/ServerSidebar.props';

/**
 * Dispatcher — renders the sidebar variant matching the resolved config.
 * Each variant is its own file (< 300 lines) in `./sidebar/`.
 *
 * - position='dock'  → floating bottom-center (DockBar)
 * - position='top'   → 2-row top bar with back/context/user + nav tabs (TopTabsBar)
 * - position='left'  → vertical panel with Classic/Rail + mobile drawer (LeftSidebar)
 */
export function ServerSidebar({ server, sidebarConfig }: ServerSidebarProps) {
    const defaultConfig = useSidebarConfig();
    const config = sidebarConfig ?? defaultConfig;

    if (config.position === 'dock') return <DockBar server={server} config={config} />;
    if (config.position === 'top') return <TopTabsBar server={server} config={config} />;
    return <LeftSidebar server={server} config={config} />;
}
