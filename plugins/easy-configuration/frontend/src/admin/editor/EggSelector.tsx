import clsx from 'clsx';
import { Check, Search } from 'lucide-react';
import { useState } from 'react';
import { useT } from '../../lib/i18n';
import type { EggOption } from '../../types';
import { Spinner } from '../../ui/surfaces';
import { Input } from '../../ui/inputs';

interface Props {
    value: number[];
    onChange: (ids: number[]) => void;
    eggs: EggOption[];
    loading?: boolean;
}

/** Multi-select egg picker showing each egg's banner image + name (searchable). */
export function EggSelector({ value, onChange, eggs, loading }: Props) {
    const { t } = useT();
    const [query, setQuery] = useState('');

    const filtered = eggs.filter((egg) => egg.name.toLowerCase().includes(query.trim().toLowerCase()));

    const toggle = (id: number): void => {
        onChange(value.includes(id) ? value.filter((v) => v !== id) : [...value, id]);
    };

    return (
        <div className="ec-field-group">
            <label>{t('admin.editor.target_eggs')}</label>
            <div className="ec-search">
                <span className="ec-search-icon">
                    <Search size={14} />
                </span>
                <Input value={query} placeholder={t('admin.editor.search_eggs')} onChange={(event) => setQuery(event.target.value)} />
            </div>

            {loading ? (
                <div className="ec-row ec-muted">
                    <Spinner /> {t('common.loading')}
                </div>
            ) : (
                <div className="ec-egg-list">
                    {filtered.map((egg) => {
                        const selected = value.includes(egg.id);

                        return (
                            <button
                                key={egg.id}
                                type="button"
                                className={clsx('ec-server-row', selected && 'ec-server-row-on')}
                                onClick={() => toggle(egg.id)}
                            >
                                {egg.banner_image ? (
                                    <img className="ec-server-thumb" src={egg.banner_image} alt="" />
                                ) : (
                                    <span className="ec-server-thumb" />
                                )}
                                <span className="ec-grow ec-truncate">{egg.name}</span>
                                <span className="ec-muted">#{egg.id}</span>
                                {selected && <Check size={16} />}
                            </button>
                        );
                    })}
                    {filtered.length === 0 && <div className="ec-empty">{t('admin.editor.no_eggs')}</div>}
                </div>
            )}
        </div>
    );
}
