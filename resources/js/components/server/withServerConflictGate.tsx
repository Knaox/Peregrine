import { useParams } from 'react-router-dom';
import { useServer } from '@/hooks/useServer';
import { Spinner } from '@/components/ui/Spinner';
import { getServerConflictKind } from '@/utils/serverConflictState';
import { ServerConflictScreen } from './ServerConflictScreen';

/**
 * Higher-order component that gates a server-detail page behind the
 * server's conflict state.
 *
 * Wraps `Component` so that on every render :
 *   1. Read the `:id` route param (every server page lives under
 *      /servers/:id, so this is always present).
 *   2. Pull the cached server row via `useServer(id)` — the same hook
 *      `ServerDetailPage` uses, so we ride on the existing query.
 *   3. If the server is in a conflict state (suspended / provisioning /
 *      provisioning_failed), render `<ServerConflictScreen>` instead
 *      of the page. Otherwise pass through to the wrapped component.
 *
 * Re-renders automatically on `mirror.changed` because
 * `useServerLiveUpdates` (registered in `ServerDetailPage`) invalidates
 * `['servers', id]` (the exact key `useServer` uses) whenever the
 * broadcast lands. The HOC re-evaluates, the gate flips on/off without
 * a page reload — exactly matching what Pterodactyl's `InstallListener`
 * + `ConflictStateRenderer` do over SocketIO, but on Reverb instead.
 *
 * NB: that invalidation is fully effective only when Reverb is actually
 * running and `BROADCAST_CONNECTION=reverb`. If the broadcaster is set
 * to `log` or `null`, every status change becomes a refresh-only event
 * and the gate falls back to the React Query `staleTime` refetch.
 *
 * NOT applied to:
 *   - `ServerOverviewPage` — has its own status-aware `SuspendedOverview`
 *     / `InstallationOverview` swap and stays accessible.
 *   - `ServerConsolePage` — install logs need to keep streaming during
 *     `provisioning`; the page handles its own AlertBanner / disabled
 *     controls inline (Pterodactyl's admin-bypass pattern).
 *
 * Pattern reuse: this is the single binding everyone calls. Adding a
 * new conflict-gated page is a one-line export change.
 */
export function withServerConflictGate<P extends object>(
    Component: React.ComponentType<P>,
): React.ComponentType<P> {
    const Wrapped = (props: P) => {
        const params = useParams<{ id: string }>();
        const serverId = Number(params.id ?? '0');
        const { data: server, isLoading } = useServer(serverId);

        // While the server is loading for the FIRST time we show a
        // spinner — without this guard a fast-refetch transition
        // (e.g. just after `.mirror.changed` invalidation) would
        // briefly flash the page contents before the gate kicks in.
        // Pterodactyl tripped on this exact bug (issue #4322 "infinite
        // spinner on suspended server") so we mirror their guard:
        // never render the conflict screen until we have a server row.
        if (isLoading || !server) {
            return (
                <div className="flex items-center justify-center py-20">
                    <Spinner size="lg" />
                </div>
            );
        }

        const kind = getServerConflictKind(server);
        if (kind !== null) {
            return <ServerConflictScreen server={server} kind={kind} />;
        }

        return <Component {...props} />;
    };

    // Preserve a debug-friendly name in React DevTools so the gate
    // doesn't show up as anonymous when something blows up upstream.
    const inner = Component.displayName ?? Component.name ?? 'Component';
    Wrapped.displayName = `withServerConflictGate(${inner})`;

    return Wrapped;
}
