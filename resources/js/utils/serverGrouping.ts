import type { Server } from '@/types/Server';

export interface ServerGroup {
    name: string;
    servers: Server[];
    eggImage?: string | null;
}

const STATUS_ORDER: Record<string, number> = {
    running: 0, active: 0, starting: 1, stopped: 2, offline: 3, suspended: 4, terminated: 5,
};

export function sortServers(servers: Server[], sortBy: string): Server[] {
    const sorted = [...servers];
    switch (sortBy) {
        case 'name':
            return sorted.sort((a, b) => a.name.localeCompare(b.name));
        case 'status':
            return sorted.sort((a, b) => (STATUS_ORDER[a.status] ?? 9) - (STATUS_ORDER[b.status] ?? 9));
        case 'created_at':
            return sorted.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
        case 'egg':
            return sorted.sort((a, b) => (a.egg?.name ?? '').localeCompare(b.egg?.name ?? ''));
        default:
            return sorted;
    }
}

export function groupServers(servers: Server[], groupBy: string, uncategorizedLabel: string): ServerGroup[] {
    if (groupBy === 'none') {
        return [{ name: '', servers, eggImage: null }];
    }

    const keyFn = (s: Server): string => {
        switch (groupBy) {
            case 'egg': return s.egg?.name ?? uncategorizedLabel;
            case 'status': return s.status;
            case 'plan': return s.plan?.name ?? uncategorizedLabel;
            default: return uncategorizedLabel;
        }
    };

    const groups = new Map<string, { servers: Server[]; eggImage?: string | null }>();
    for (const server of servers) {
        const key = keyFn(server);
        const existing = groups.get(key);
        if (existing) {
            existing.servers.push(server);
        } else {
            groups.set(key, { servers: [server], eggImage: server.egg?.banner_image });
        }
    }
    return Array.from(groups.entries()).map(([name, data]) => ({
        name,
        servers: data.servers,
        eggImage: data.eggImage,
    }));
}
