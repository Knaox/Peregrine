import { useCallback, useMemo, useState } from 'react';
import { fieldKey } from '../../lib/fieldKey';
import { pickLabel } from '../../lib/i18n';
import type { ConfigTemplate } from '../../types';
import type { BoostableParam } from './BoostDialog';
import type { Boost } from './useBoosts';

/**
 * Boost selection state for the editor: which numeric parameters are ticked for
 * a new boost, which are already covered by a pending/active boost (shown ticked
 * + locked so a selection visibly persists), and the params a new boost will
 * apply to. Extracted from ConfigEditor to keep that component under the
 * 300-line limit.
 */
export function useBoostSelection(templates: ConfigTemplate[], boostsData: Boost[] | undefined, lang: string, canBoost: boolean) {
    const [boostMode, setBoostMode] = useState(false);
    const [boostSelected, setBoostSelected] = useState<Set<string>>(new Set());
    // Subset of boostSelected ticked as "divide" (deboost) rather than multiply.
    const [boostDivide, setBoostDivide] = useState<Set<string>>(new Set());

    const boostEnabled = templates.some((template) => template.boost_enabled) && canBoost;

    const boostedKeys = useMemo(
        () => new Set((boostsData ?? []).flatMap((boost) => boost.parameters.map((p) => fieldKey(p.file_id, p.section, p.key)))),
        [boostsData],
    );

    const boostableParams = useMemo<BoostableParam[]>(() => {
        const out: BoostableParam[] = [];
        for (const template of templates) {
            if (!template.boost_enabled) {
                continue;
            }
            for (const file of template.files) {
                for (const param of file.parameters) {
                    if ((param.display_type !== 'number' && param.display_type !== 'slider') || template.boost_blacklist.includes(param.key)) {
                        continue;
                    }
                    out.push({
                        template_id: template.id,
                        file_id: file.id,
                        section: param.section,
                        key: param.key,
                        label: pickLabel(param.label, lang, param.key),
                        max: typeof param.config.max === 'number' ? param.config.max : undefined,
                    });
                }
            }
        }

        return out;
    }, [templates, lang]);

    const boostableKeys = useMemo(() => new Set(boostableParams.map((p) => fieldKey(p.file_id, p.section, p.key))), [boostableParams]);

    const selectedBoostParams = useMemo(
        () => boostableParams
            .filter((p) => {
                const key = fieldKey(p.file_id, p.section, p.key);
                return boostSelected.has(key) && !boostedKeys.has(key);
            })
            .map((p) => ({ ...p, invert: boostDivide.has(fieldKey(p.file_id, p.section, p.key)) })),
        [boostableParams, boostSelected, boostedKeys, boostDivide],
    );

    const toggleBoost = useCallback((key: string): void => {
        if (boostedKeys.has(key)) {
            return; // already boosted — locked
        }
        setBoostSelected((current) => {
            const next = new Set(current);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
        // Deselecting a parameter also clears its divide flag.
        setBoostDivide((current) => {
            if (!current.has(key)) {
                return current;
            }
            const next = new Set(current);
            next.delete(key);

            return next;
        });
    }, [boostedKeys]);

    const toggleDivide = useCallback((key: string): void => {
        setBoostDivide((current) => {
            const next = new Set(current);
            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    }, []);

    const setMode = useCallback((on: boolean): void => {
        setBoostMode(on);
        if (!on) {
            setBoostSelected(new Set());
            setBoostDivide(new Set());
        }
    }, []);

    return {
        boostMode,
        setMode,
        boostEnabled,
        selectedBoostParams,
        toggleBoost,
        toggleDivide,
        isBoostable: (key: string) => boostableKeys.has(key),
        isBoostSelected: (key: string) => boostSelected.has(key) || boostedKeys.has(key),
        isBoostLocked: (key: string) => boostedKeys.has(key),
        isBoostDivide: (key: string) => boostDivide.has(key),
    };
}
