import { Download, Egg, FilePlus2, LayoutTemplate, PackageOpen, Pencil, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { pickLabel, useT } from '../lib/i18n';
import { ADMIN_PATH, BASE, type ApiError } from '../shared';
import { Button, IconButton } from '../ui/Button';
import { Dialog } from '../ui/Dialog';
import { Textarea } from '../ui/inputs';
import { Badge, Card, EmptyState, Spinner } from '../ui/surfaces';
import { useToast } from '../ui/Toast';
import { useDeleteTemplate, useImportOfficialTemplates, useImportTemplate, useImportTemplateEgg, useTemplateList } from './hooks/useTemplates';

export function TemplateListPage() {
    const { t, lang } = useT();
    const navigate = useNavigate();
    const toast = useToast();
    const list = useTemplateList();
    const remove = useDeleteTemplate();
    const importer = useImportTemplate();
    const official = useImportOfficialTemplates();
    const eggImport = useImportTemplateEgg();
    const [importOpen, setImportOpen] = useState(false);
    const [importText, setImportText] = useState('');
    const [importingEggFor, setImportingEggFor] = useState<string | null>(null);

    if (list.isError && (list.error as unknown as ApiError | null)?.status === 403) {
        return (
            <div className="ec-page">
                <Card>
                    <EmptyState>{t('admin.unauthorized')}</EmptyState>
                </Card>
            </div>
        );
    }

    const onDelete = (id: string): void => {
        if (!window.confirm(t('admin.list.confirm_delete', { id }))) {
            return;
        }
        remove.mutate(id, {
            onSuccess: () => toast.success(t('admin.list.deleted')),
            onError: () => toast.error(t('errors.generic')),
        });
    };

    const onImport = (): void => {
        importer.mutate(importText, {
            onSuccess: () => {
                toast.success(t('admin.list.imported'));
                setImportOpen(false);
                setImportText('');
            },
            onError: (error) => toast.error((error as unknown as ApiError).message ?? t('admin.list.import_failed')),
        });
    };

    const onImportOfficial = (): void => {
        official.mutate(undefined, {
            onSuccess: (result) => toast.success(t('admin.list.official_imported', { ok: result.imported.length, skipped: result.skipped.length })),
            onError: (error) => toast.error((error as unknown as ApiError).message ?? t('admin.list.official_failed')),
        });
    };

    const onImportEgg = (id: string): void => {
        setImportingEggFor(id);
        eggImport.mutate(id, {
            onSuccess: (result) => {
                toast.success(result.updated ? t('admin.egg.updated') : t('admin.egg.imported'));
                if (result.attached_egg_id !== null) {
                    toast.show(t('admin.egg.attached'), 'info');
                }
            },
            onError: (error) => toast.error((error as unknown as ApiError).message ?? t('admin.egg.failed')),
            onSettled: () => setImportingEggFor(null),
        });
    };

    return (
        <div className="ec-page">
            <div className="ec-between">
                <div>
                    <h1 className="ec-title">{t('admin.list.title')}</h1>
                    <p className="ec-subtitle">{t('admin.list.subtitle')}</p>
                </div>
                <div className="ec-row">
                    <Button variant="ghost" onClick={() => navigate(`${ADMIN_PATH}/example`)}>
                        <LayoutTemplate size={15} /> {t('admin.list.example')}
                    </Button>
                    <Button variant="secondary" loading={official.isPending} onClick={onImportOfficial}>
                        <PackageOpen size={15} /> {t('admin.list.import_official')}
                    </Button>
                    <Button variant="secondary" onClick={() => setImportOpen(true)}>
                        <Upload size={15} /> {t('admin.list.import')}
                    </Button>
                    <Button onClick={() => navigate(`${ADMIN_PATH}/new`)}>
                        <FilePlus2 size={15} /> {t('admin.list.new')}
                    </Button>
                </div>
            </div>

            {list.isLoading ? (
                <div className="ec-row ec-muted">
                    <Spinner /> {t('common.loading')}
                </div>
            ) : list.data && list.data.length > 0 ? (
                <div className="ec-grid">
                    {list.data.map((tpl) => (
                        <Card key={tpl.template_id} hover className="ec-template-card">
                            <div className="ec-between">
                                <strong className="ec-truncate">{pickLabel(tpl.name, lang, tpl.template_id)}</strong>
                                {tpl.is_valid ? <Badge variant="success">v{tpl.version}</Badge> : <Badge variant="warning">{t('admin.list.invalid')}</Badge>}
                            </div>
                            <span className="ec-subtitle ec-truncate">{tpl.template_id}</span>
                            {!tpl.is_valid && tpl.last_error !== null && <span className="ec-field-desc ec-truncate">{tpl.last_error}</span>}
                            <div className="ec-template-card-foot">
                                <Badge variant="muted">{t('admin.list.files', { count: tpl.file_count })}</Badge>
                                <Badge variant="muted">{t('admin.list.eggs', { count: tpl.target_eggs.length })}</Badge>
                                {tpl.boost_enabled && <Badge variant="accent">{t('admin.list.boost')}</Badge>}
                            </div>
                            <div className="ec-template-card-actions">
                                <Button size="sm" variant="secondary" onClick={() => navigate(`${ADMIN_PATH}/${tpl.template_id}`)}>
                                    <Pencil size={13} /> {t('common.edit')}
                                </Button>
                                {tpl.has_egg === true && (
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        loading={importingEggFor === tpl.template_id}
                                        title={t('admin.egg.hint')}
                                        onClick={() => onImportEgg(tpl.template_id)}
                                    >
                                        <Egg size={13} /> {t('admin.egg.button')}
                                    </Button>
                                )}
                                <a className="ec-btn ec-btn-ghost ec-btn-sm" href={`${BASE}/admin/templates/${tpl.template_id}/export`}>
                                    <Download size={13} /> {t('common.export')}
                                </a>
                                <IconButton label={t('common.delete')} onClick={() => onDelete(tpl.template_id)}>
                                    <Trash2 size={14} />
                                </IconButton>
                            </div>
                        </Card>
                    ))}
                </div>
            ) : (
                <Card>
                    <EmptyState>{t('admin.list.empty')}</EmptyState>
                </Card>
            )}

            <Dialog
                open={importOpen}
                onClose={() => setImportOpen(false)}
                closeLabel={t('common.close')}
                title={t('admin.list.import_title')}
                footer={
                    <>
                        <Button variant="ghost" onClick={() => setImportOpen(false)}>
                            {t('common.cancel')}
                        </Button>
                        <Button loading={importer.isPending} onClick={onImport}>
                            {t('admin.list.import')}
                        </Button>
                    </>
                }
            >
                <div className="ec-dialog-body">
                    <Textarea className="ec-mono" value={importText} placeholder='{ "id": "minecraft-vanilla", ... }' onChange={(event) => setImportText(event.target.value)} />
                </div>
            </Dialog>
        </div>
    );
}
