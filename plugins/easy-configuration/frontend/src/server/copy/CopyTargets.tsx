import clsx from 'clsx';
import { Check, Search } from 'lucide-react';
import { useState } from 'react';
import { useT } from '../../lib/i18n';
import { Input } from '../../ui/inputs';
import { Badge, Spinner } from '../../ui/surfaces';
import type { CopyTarget } from './useCopy';

interface Props {
    targets: CopyTarget[];
    selected: Set<number>;
    onToggle: (id: number) => void;
    loading: boolean;
}

/** Step 1: pick the destination servers (same egg). Running servers are disabled. */
export function CopyTargets({ targets, selected, onToggle, loading }: Props) {
    const { t } = useT();
    const [query, setQuery] = useState('');
    const filtered = targets.filter((server) => server.name.toLowerCase().includes(query.trim().toLowerCase()));

    if (loading) {
        return (
            <div className="ec-row ec-muted">
                <Spinner /> {t('common.loading')}
            </div>
        );
    }

    if (targets.length === 0) {
        return <div className="ec-empty">{t('copy.no_targets')}</div>;
    }

    return (
        <div className="ec-stack">
            <div className="ec-search">
                <span className="ec-search-icon">
                    <Search size={14} />
                </span>
                <Input value={query} placeholder={t('copy.search_servers')} onChange={(event) => setQuery(event.target.value)} />
            </div>

            <div className="ec-egg-list">
                {filtered.map((server) => {
                    const on = selected.has(server.id);

                    return (
                        <button
                            key={server.id}
                            type="button"
                            disabled={server.running}
                            title={server.running ? t('copy.running_tip') : undefined}
                            className={clsx('ec-server-row', on && 'ec-server-row-on', server.running && 'ec-server-row-disabled')}
                            onClick={() => !server.running && onToggle(server.id)}
                        >
                            {server.egg.banner_image ? (
                                <img className="ec-server-thumb" src={server.egg.banner_image} alt="" />
                            ) : (
                                <span className="ec-server-thumb" />
                            )}
                            <span className="ec-grow">
                                <span className="ec-truncate">{server.name}</span>
                                <span className="ec-field-desc ec-muted">
                                    {server.egg.name ?? '?'} · {server.identifier}
                                </span>
                            </span>
                            {server.running ? <Badge variant="warning">{t('copy.running')}</Badge> : on && <Check size={16} />}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
