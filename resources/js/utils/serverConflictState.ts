import type { Server } from '@/types/Server';

/**
 * Centralised "is this server in a conflict state" selector.
 *
 * A conflict state is one where most server pages should NOT render
 * normally — either because the server hasn't finished installing yet
 * (provisioning), the install just blew up (provisioning_failed), or
 * the operator/billing system put it in suspended.
 *
 * Mirrors Pterodactyl's `inConflictState` and Pelican's
 * `isInConflictState()` from the panel source — same UX intent,
 * single source of truth on our side. Sidebar gating in
 * `ServerDetailPage.tsx`, the per-page `withServerConflictGate` HOC,
 * and the dashboard pill all consume this so adding a new state
 * (e.g. `migrating`) is a one-line change here.
 */
export type ServerConflictKind =
    | 'suspended'
    | 'provisioning'
    | 'provisioning_failed'
    | null;

/**
 * Returns the specific conflict kind, or `null` when the server is
 * fine to interact with normally. The order matters: `suspended`
 * outranks `provisioning` because admin suspension is a hard lock and
 * we want the user-visible message to reflect that even mid-install.
 */
export function getServerConflictKind(
    server: Server | null | undefined,
): ServerConflictKind {
    if (server == null) {
        return null;
    }
    if (server.status === 'suspended') {
        return 'suspended';
    }
    if (server.status === 'provisioning') {
        return 'provisioning';
    }
    if (server.status === 'provisioning_failed') {
        return 'provisioning_failed';
    }
    return null;
}

/**
 * Boolean shortcut for callers that don't need to differentiate
 * between the conflict kinds — typically the dashboard card gating
 * its resource-stats polling.
 */
export function isServerInConflict(
    server: Server | null | undefined,
): boolean {
    return getServerConflictKind(server) !== null;
}
