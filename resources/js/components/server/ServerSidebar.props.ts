import type { Server } from '@/types/Server';
import type { SidebarConfig } from '@/hooks/useSidebarConfig';

export interface ServerSidebarProps {
    server: Server;
    sidebarConfig?: SidebarConfig;
}
