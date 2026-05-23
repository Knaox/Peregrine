import { useState } from 'react';
import { ParameterForm } from '../admin/editor/visual/ParameterForm';
import { useAnnotateTemplateParameter, type AnnotateParameterInput } from '../admin/hooks/useTemplates';
import { useT } from '../lib/i18n';
import { getParam } from '../lib/paramEdit';
import type { Json } from '../lib/templateFiles';
import type { ApiError } from '../shared';
import type { ConfigParam } from '../types';
import { Button } from '../ui/Button';
import { Callout } from '../ui/Callout';
import { Dialog } from '../ui/Dialog';
import { useToast } from '../ui/Toast';

interface Props {
    serverId: number;
    templateId: string;
    fileId: string;
    param: ConfigParam;
    onClose: () => void;
}

/** Seed an in-memory template `file` with the detected type + current value. */
function seedFile(param: ConfigParam): Json {
    const def: Json = { display_type: param.display_type };
    if (param.value !== '') {
        def.config = { default: param.value };
    }

    return param.section === null
        ? { parameters: { [param.key]: def } }
        : { parameters: { [param.section]: { [param.key]: def } } };
}

/**
 * Admin-only: promote a discovered (inferred) parameter into the template. Reuses
 * the visual editor's ParameterForm on an in-memory file seeded from the field;
 * on save, sends just this parameter to the template so it gains a curated
 * label/description/type for every server of the egg. Mounted on demand so it
 * always opens with fresh state.
 */
export function AnnotateParameterDialog({ serverId, templateId, fileId, param, onClose }: Props) {
    const { t } = useT();
    const toast = useToast();
    const annotate = useAnnotateTemplateParameter(serverId);
    const [file, setFile] = useState<Json>(() => seedFile(param));

    const submit = (): void => {
        const def = getParam(file, param.section, param.key);
        if (def === null) {
            return;
        }

        const payload: AnnotateParameterInput = {
            file_id: fileId,
            section: param.section,
            key: param.key,
            display_type: String(def.display_type ?? 'text'),
            label: (def.label ?? null) as Record<string, string> | null,
            description: (def.description ?? null) as Record<string, string> | null,
            config: (def.config ?? null) as Record<string, unknown> | null,
            env_var: typeof def.env_var === 'string' && def.env_var !== '' ? def.env_var : null,
        };

        annotate.mutate(
            { templateId, param: payload },
            {
                onSuccess: () => {
                    toast.success(t('annotate.saved'));
                    onClose();
                },
                onError: (error) => toast.error((error as unknown as ApiError)?.message ?? t('annotate.failed')),
            },
        );
    };

    return (
        <Dialog
            open
            onClose={onClose}
            closeLabel={t('common.close')}
            title={t('annotate.title')}
            footer={
                <>
                    <Button variant="ghost" onClick={onClose}>{t('common.cancel')}</Button>
                    <Button loading={annotate.isPending} onClick={submit}>{t('common.save')}</Button>
                </>
            }
        >
            <div className="ec-dialog-body">
                <Callout variant="info">
                    {t('annotate.intro', { key: param.section ? `${param.section} · ${param.key}` : param.key })}
                </Callout>
                <ParameterForm file={file} section={param.section} paramKey={param.key} datalistId="ec-annotate-envvars" onChange={setFile} />
            </div>
        </Dialog>
    );
}
