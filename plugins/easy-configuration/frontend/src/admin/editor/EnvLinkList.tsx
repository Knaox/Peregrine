import { Plus, X } from 'lucide-react';
import { useT } from '../../lib/i18n';
import { Button, IconButton } from '../../ui/Button';
import { Input, Select } from '../../ui/inputs';
import type { PromptEnvLink } from './buildPrompt';

export interface ParamOption {
    id: string;
    fileId: string;
    section: string | null;
    key: string;
    label: string;
}

const SEP = '';
const refOf = (link: { fileId: string; section: string | null; key: string }): string => `${link.fileId}${SEP}${link.section ?? ''}${SEP}${link.key}`;

/**
 * Explicit env_var ↔ parameter links for the prompt generator. Each row picks a
 * parameter (from every detected param across the imported files) and types the
 * egg's env variable name (autocompleted via the shared datalist). Only the few
 * links the admin cares about need to be added — not all parameters.
 */
export function EnvLinkList({ value, onChange, paramOptions, datalistId }: { value: PromptEnvLink[]; onChange: (links: PromptEnvLink[]) => void; paramOptions: ParamOption[]; datalistId: string }) {
    const { t } = useT();

    const setRow = (index: number, next: PromptEnvLink): void => onChange(value.map((link, i) => (i === index ? next : link)));
    const removeRow = (index: number): void => onChange(value.filter((_, i) => i !== index));

    const addRow = (): void => {
        const first = paramOptions[0];
        if (first === undefined) {
            return;
        }
        onChange([...value, { fileId: first.fileId, section: first.section, key: first.key, envVar: '' }]);
    };

    const pickParam = (index: number, ref: string): void => {
        const option = paramOptions.find((o) => o.id === ref);
        if (option !== undefined) {
            setRow(index, { ...value[index], fileId: option.fileId, section: option.section, key: option.key });
        }
    };

    return (
        <div className="ec-stack">
            {value.map((link, index) => (
                <div key={index} className="ec-row">
                    <Select value={refOf(link)} onChange={(ref) => pickParam(index, ref)}>
                        {paramOptions.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                    <Input list={datalistId} value={link.envVar} placeholder={t('admin.prompt.envvar_ph')} onChange={(e) => setRow(index, { ...link, envVar: e.target.value })} />
                    <IconButton label={t('common.delete')} onClick={() => removeRow(index)}>
                        <X size={14} />
                    </IconButton>
                </div>
            ))}
            <div>
                <Button variant="ghost" size="sm" disabled={paramOptions.length === 0} onClick={addRow}>
                    <Plus size={14} /> {t('admin.prompt.add_link')}
                </Button>
            </div>
        </div>
    );
}
