import { useMutation, useQuery } from '@tanstack/react-query';
import { api, BASE, type PmaDatabase } from './shared';
import { useT } from './lib/i18n';

function DbIcon() {
    return (
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round">
            <ellipse cx="12" cy="5" rx="8" ry="3" />
            <path d="M4 5v14c0 1.66 3.58 3 8 3s8-1.34 8-3V5" />
            <path d="M4 12c0 1.66 3.58 3 8 3s8-1.34 8-3" />
        </svg>
    );
}

interface PmaState {
    enabled: boolean;
    auto_select_db: boolean;
}

/**
 * Row action injected into the core "Databases" tab via
 * `registerDatabaseRowAction`. Hidden when the integration is disabled. On
 * click it asks Peregrine for a signon URL and opens phpMyAdmin in a new tab.
 */
export function PmaButton({ serverId, database }: { serverId: number; database: PmaDatabase }) {
    const t = useT();

    const { data: state } = useQuery({
        queryKey: ['pma', 'state'],
        queryFn: () => api<PmaState>(`${BASE}/state`),
        staleTime: 300_000,
    });

    const launch = useMutation({
        mutationFn: () =>
            api<{ url: string }>(`${BASE}/servers/${serverId}/databases/${database.id}/launch`, { method: 'POST' }),
        onSuccess: ({ url }) => {
            window.open(url, '_blank', 'noopener,noreferrer');
        },
    });

    if (!state?.enabled) {
        return null;
    }

    return (
        <button
            type="button"
            className="pma-btn"
            disabled={launch.isPending}
            onClick={() => launch.mutate()}
            title={t('button.open_title', { name: database.name })}
        >
            <DbIcon />
            <span>{launch.isPending ? t('button.opening') : t('button.open')}</span>
        </button>
    );
}
