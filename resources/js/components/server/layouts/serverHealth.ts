/**
 * Maps a server runtime/lifecycle state to a CSS-variable-driven color
 * and a one-word label key. Centralised so all three new dashboard
 * layouts (CommandBar, BentoMosaic, PulseGrid) tint statuses identically
 * — and so admins changing the theme palette in /theme-studio see the
 * dashboards update without touching every layout file.
 */
export interface ServerHealth {
    /** Resolves to a CSS color string consuming `--color-*` vars. */
    color: string;
    /** i18n key suffix for `servers.status.*`. */
    labelKey: string;
    /** True when the server is actively running — drives pulse animation. */
    isAlive: boolean;
}

export function resolveHealthColor(state: string): ServerHealth {
    switch (state) {
        case 'running':
        case 'active':
            return { color: 'var(--color-success)', labelKey: 'running', isAlive: true };
        case 'starting':
            return { color: 'var(--color-info)', labelKey: 'starting', isAlive: true };
        case 'provisioning':
            return { color: 'var(--color-installing)', labelKey: 'provisioning', isAlive: false };
        case 'provisioning_failed':
            return { color: 'var(--color-danger)', labelKey: 'provisioning_failed', isAlive: false };
        case 'suspended':
            return { color: 'var(--color-suspended)', labelKey: 'suspended', isAlive: false };
        case 'terminated':
            return { color: 'var(--color-danger)', labelKey: 'terminated', isAlive: false };
        case 'stopped':
        case 'offline':
        default:
            return { color: 'var(--color-text-muted)', labelKey: 'stopped', isAlive: false };
    }
}
