import { useState } from 'react';
import { useT } from '../lib/i18n';
import type { ApiError } from '../shared';
import type { ConfigParam } from '../types';
import { Button } from '../ui/Button';
import { Dialog } from '../ui/Dialog';
import { Input, Select } from '../ui/inputs';
import { useToast } from '../ui/Toast';
import { useAddParameter } from './hooks/useServerConfig';

interface Props {
    open: boolean;
    onClose: () => void;
    serverId: number;
    fileId: string;
    /** Native sections of the file (ini/toml); empty for flat files. */
    sections: string[];
    /** Existing parameters of the file — used to append after repeated keys. */
    params: ConfigParam[];
}

/**
 * Adds a brand-new key to a config file (into a chosen section for ini/toml).
 * The backend appends it; the config refetch then shows it as an editable
 * auto-detected field. For values that don't fit a free-text key, the admin can
 * still describe them properly in the template afterwards.
 */
export function AddParameterDialog({ open, onClose, serverId, fileId, sections, params }: Props) {
    const { t } = useT();
    const toast = useToast();
    const add = useAddParameter(serverId);
    const [key, setKey] = useState('');
    const [value, setValue] = useState('');
    const [section, setSection] = useState<string>(sections[0] ?? '');

    const submit = (): void => {
        const trimmedKey = key.trim();
        if (trimmedKey === '') {
            toast.error(t('add_param.key_required'));

            return;
        }
        const targetSection = sections.length > 0 ? section || null : null;
        // Append AFTER any existing copies of this (section, key): a repeatable
        // key (e.g. ConfigOverrideItemMaxQuantity) gets a NEW line instead of
        // overwriting the first. A brand-new key has count 0 → still appended.
        const occurrence = params.filter((p) => p.key === trimmedKey && p.section === targetSection).length;
        add.mutate(
            { fileId, key: trimmedKey, section: targetSection, value, occurrence },
            {
                onSuccess: () => {
                    toast.success(t('add_param.added'));
                    setKey('');
                    setValue('');
                    onClose();
                },
                onError: (error) => toast.error((error as unknown as ApiError)?.message ?? t('add_param.failed')),
            },
        );
    };

    return (
        <Dialog
            open={open}
            onClose={onClose}
            closeLabel={t('common.close')}
            title={t('add_param.title')}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button>
                    <Button loading={add.isPending} onClick={submit}>{t('add_param.add')}</Button>
                </>
            }
        >
            <div className="ec-dialog-body">
                <p className="ec-field-desc ec-muted">{t('add_param.intro')}</p>
                {sections.length > 0 && (
                    <div className="ec-field-group">
                        <label>{t('add_param.section')}</label>
                        <Select value={section} onChange={setSection}>
                            {sections.map((s) => (
                                <option key={s} value={s}>{s}</option>
                            ))}
                        </Select>
                    </div>
                )}
                <div className="ec-field-group">
                    <label>{t('add_param.key')}</label>
                    <Input value={key} onChange={(e) => setKey(e.target.value)} placeholder="MaxPlayers" />
                </div>
                <div className="ec-field-group">
                    <label>{t('add_param.value')}</label>
                    <Input value={value} onChange={(e) => setValue(e.target.value)} />
                </div>
            </div>
        </Dialog>
    );
}
