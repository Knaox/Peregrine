import type { Server } from '@/types/Server';

interface ServerPermissions {
    /** True if the user is the server owner (implicit all permissions). */
    isOwner: boolean;
    /** True if the user has the given permission key (owners: always true). */
    has: (permission: string) => boolean;
    /** True if the user has any of the given permission keys. */
    hasAny: (permissions: string[]) => boolean;
    /** True if the user has every one of the given permission keys. */
    hasAll: (permissions: string[]) => boolean;
}

/**
 * Reads server.role + server.permissions (populated by serverApi.ts) and exposes
 * a stable boolean API for gating UI controls. Owners always receive `true`.
 */
export function useServerPermissions(server: Server | null | undefined): ServerPermissions {
    const role = server?.role;
    const permissions = server?.permissions ?? null;
    const isOwner = !server || !role || role === 'owner' || permissions === null;

    const has = (permission: string): boolean => {
        if (isOwner) return true;
        return Array.isArray(permissions) && permissions.includes(permission);
    };

    const hasAny = (list: string[]): boolean => {
        if (isOwner) return true;
        if (!Array.isArray(permissions)) return false;
        return list.some(p => permissions.includes(p));
    };

    const hasAll = (list: string[]): boolean => {
        if (isOwner) return true;
        if (!Array.isArray(permissions)) return false;
        return list.every(p => permissions.includes(p));
    };

    return { isOwner, has, hasAny, hasAll };
}
