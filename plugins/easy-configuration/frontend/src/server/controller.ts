import type { ConfigParam } from '../types';

/** Field-level state + callbacks threaded from ConfigEditor down to each FieldRow. */
export interface EditorController {
    getValue: (fieldKey: string) => string;
    isDirty: (fieldKey: string) => boolean;
    isSaved: (fieldKey: string) => boolean;
    isInvalid: (fieldKey: string) => boolean;
    disabled: boolean;
    search: string;
    onChange: (fieldKey: string, param: ConfigParam, value: string) => void;
    onReset: (fieldKey: string, param: ConfigParam) => void;
    // Boost selection: when boostMode is on, boostable parameters show a
    // checkbox so the player picks which ones to boost directly in the editor.
    boostMode: boolean;
    isBoostable: (fieldKey: string) => boolean;
    isBoostSelected: (fieldKey: string) => boolean;
    /** Already covered by a pending/active boost: shown ticked but locked. */
    isBoostLocked: (fieldKey: string) => boolean;
    toggleBoost: (fieldKey: string) => void;
    /** Per-parameter "divide" (deboost) flag for a ticked boost parameter. */
    isBoostDivide: (fieldKey: string) => boolean;
    toggleDivide: (fieldKey: string) => void;
    /** Admin: may annotate a discovered (inferred) parameter into the template. */
    canManageTemplate: boolean;
}
