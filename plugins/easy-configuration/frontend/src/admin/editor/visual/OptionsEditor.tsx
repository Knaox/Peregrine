import { Plus, Trash2 } from 'lucide-react';
import { useT } from '../../../lib/i18n';
import type { OptionDraft } from '../../../lib/paramEdit';
import { Button, IconButton } from '../../../ui/Button';
import { Input } from '../../../ui/inputs';

/**
 * Edits the `options` of a select / multiselect parameter: one row per option
 * (raw value + optional EN/FR label), with add and remove. Writes the whole
 * array back so the parent helper can drop it (and `config`) when empty.
 */
export function OptionsEditor({ options, onChange }: { options: OptionDraft[]; onChange: (options: OptionDraft[]) => void }) {
    const { t } = useT();

    const replace = (index: number, next: OptionDraft): void => onChange(options.map((o, i) => (i === index ? next : o)));

    const setLabel = (index: number, lang: 'en' | 'fr', value: string): void => {
        const current = options[index];
        if (current === undefined) {
            return;
        }
        const label = { ...(current.label ?? {}) };
        if (value.trim() === '') {
            delete label[lang];
        } else {
            label[lang] = value.trim();
        }
        replace(index, { ...current, label: Object.keys(label).length > 0 ? label : undefined });
    };

    return (
        <div className="ec-stack" style={{ gap: '0.4rem' }}>
            {options.map((option, index) => (
                <div key={index} className="ec-row" style={{ gap: '0.4rem' }}>
                    <Input value={option.value} placeholder={t('admin.visual.option_value')} onChange={(e) => replace(index, { ...option, value: e.target.value })} />
                    <Input value={option.label?.en ?? ''} placeholder="EN" onChange={(e) => setLabel(index, 'en', e.target.value)} />
                    <Input value={option.label?.fr ?? ''} placeholder="FR" onChange={(e) => setLabel(index, 'fr', e.target.value)} />
                    <IconButton label={t('admin.visual.option_remove')} onClick={() => onChange(options.filter((_, i) => i !== index))}>
                        <Trash2 size={14} />
                    </IconButton>
                </div>
            ))}
            <Button size="sm" variant="ghost" onClick={() => onChange([...options, { value: '' }])}>
                <Plus size={14} /> {t('admin.visual.option_add')}
            </Button>
        </div>
    );
}
