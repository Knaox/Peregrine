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
}
