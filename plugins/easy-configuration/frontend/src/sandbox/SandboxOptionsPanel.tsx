import { useState } from 'react';
import { ChevronDown, ChevronRight, Search } from 'lucide-react';
import { useT } from '../lib/i18n';
import { Button } from '../ui/Button';
import { Input } from '../ui/inputs';
import {
    disabledSandboxOptions,
    modifiedSandboxOptions,
    optionLabel,
    SANDBOX_CATEGORIES,
    SANDBOX_OPTIONS,
    type SandboxState,
    valueIndexOf,
} from './codec';
import { SandboxOptionControl } from './SandboxOptionControl';

/**
 * The generator body: search + per-category collapsible groups of option
 * controls, mirroring the game's own sandbox screen. Purely presentational —
 * the decoded state comes in, a picked (option, valueIndex) goes out and the
 * parent re-encodes the code.
 */
export function SandboxOptionsPanel({
    state,
    disabled,
    onPick,
    onResetAll,
}: {
    state: SandboxState;
    disabled: boolean;
    onPick: (optionName: string, valueIndex: number) => void;
    onResetAll: () => void;
}) {
    const { t, lang } = useT();
    const [search, setSearch] = useState('');
    const [open, setOpen] = useState<Set<string>>(new Set(['General']));

    const query = search.trim().toLowerCase();
    const visible = query === ''
        ? SANDBOX_OPTIONS
        : SANDBOX_OPTIONS.filter(
            (option) =>
                option.option.toLowerCase().includes(query)
                || option.label.toLowerCase().includes(query)
                || optionLabel(option, lang).toLowerCase().includes(query),
        );

    const modified = new Set(modifiedSandboxOptions(state));
    const disabledByRule = disabledSandboxOptions(state);

    const toggleGroup = (category: string): void => {
        setOpen((current) => {
            const next = new Set(current);
            if (next.has(category)) {
                next.delete(category);
            } else {
                next.add(category);
            }

            return next;
        });
    };

    return (
        <div className="sbx-panel">
            <div className="sbx-toolbar">
                <div className="ec-search sbx-search">
                    <span className="ec-search-icon">
                        <Search size={13} />
                    </span>
                    <Input value={search} placeholder={t('sandbox.search')} onChange={(event) => setSearch(event.target.value)} />
                </div>
                <span className="sbx-count">{t('sandbox.modified', { count: modified.size })}</span>
                <Button variant="ghost" size="sm" disabled={disabled || modified.size === 0} onClick={onResetAll}>
                    {t('sandbox.reset_all')}
                </Button>
            </div>

            {SANDBOX_CATEGORIES.map((category) => {
                const options = visible.filter((option) => option.category === category);
                if (options.length === 0) {
                    return null;
                }
                const isOpen = query !== '' || open.has(category);
                const changed = options.filter((option) => modified.has(option.option)).length;

                return (
                    <div key={category} className="sbx-group">
                        <button type="button" className="sbx-group-head" aria-expanded={isOpen} onClick={() => toggleGroup(category)}>
                            {isOpen ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
                            <span>{t(`sandbox.category.${category}`)}</span>
                            {changed > 0 && <span className="sbx-group-count">{changed}</span>}
                        </button>
                        {isOpen && (
                            <div className="sbx-grid">
                                {options.map((option) => (
                                    <SandboxOptionControl
                                        key={option.option}
                                        option={option}
                                        valueIndex={valueIndexOf(option, state)}
                                        modified={modified.has(option.option)}
                                        disabled={disabled || disabledByRule.has(option.option)}
                                        onPick={(valueIndex) => onPick(option.option, valueIndex)}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}

            {visible.length === 0 && <p className="sbx-empty">{t('section.no_results')}</p>}
        </div>
    );
}
