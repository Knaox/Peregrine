import { StatusDot } from '@/components/ui/StatusDot';
import type { Server } from '@/types/Server';

interface ServerContextPillProps {
    server: Pick<Server, 'id' | 'name' | 'status'>;
    showStatus?: boolean;
    showName?: boolean;
}

/**
 * Shared pill that identifies the current server (name or id + status dot).
 * Used in TopTabs header and Dock top-left corner so users never lose track
 * of which server they're acting on.
 */
export function ServerContextPill({ server, showStatus = true, showName = true }: ServerContextPillProps) {
    return (
        <div
            className="flex items-center gap-2 min-h-[44px] px-3.5 py-2.5"
            style={{
                borderRadius: '9999px',
                background: 'var(--color-glass)',
                backdropFilter: 'var(--glass-blur)',
                border: '1px solid var(--color-glass-border)',
            }}
            role="status"
            aria-live="polite"
        >
            {showStatus && <StatusDot status={server.status} size="sm" />}
            <p
                className="truncate max-w-[140px] sm:max-w-[220px] text-sm font-semibold"
                style={{ color: 'var(--color-text-primary)' }}
                title={showName ? server.name : `#${server.id}`}
            >
                {showName ? server.name : `#${server.id}`}
            </p>
        </div>
    );
}
