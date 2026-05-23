import { useState } from 'react';
import { useT } from '../../lib/i18n';
import { extensionToFormat } from '../../lib/format';
import { Button } from '../../ui/Button';
import { Dialog } from '../../ui/Dialog';
import { Select } from '../../ui/inputs';
import { useToast } from '../../ui/Toast';
import { useImportConfig, useServerCatalog } from '../hooks/useTemplates';
import { ServerFileBrowser } from './ServerFileBrowser';

interface ImportFromServerDialogProps {
    open: boolean;
    onClose: () => void;
    /** Receives each scaffolded template `file` block to append to the draft. */
    onImported: (file: Record<string, unknown>) => void;
}

/**
 * Browse a server's real files and import several config files at once. The
 * admin picks a server, navigates folders, ticks config files (the selection
 * persists across navigation), then imports the selection — each file is parsed
 * server-side and appended as a section of the same template.
 */
export function ImportFromServerDialog({ open, onClose, onImported }: ImportFromServerDialogProps) {
    const { t } = useT();
    const toast = useToast();
    const servers = useServerCatalog();
    const importConfig = useImportConfig();

    const [serverId, setServerId] = useState('');
    const [directory, setDirectory] = useState('/');
    const [selected, setSelected] = useState<Set<string>>(new Set());
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const close = (): void => {
        setServerId('');
        setDirectory('/');
        setSelected(new Set());
        setError('');
        setBusy(false);
        onClose();
    };

    const onServerChange = (value: string): void => {
        setServerId(value);
        setDirectory('/');
        setSelected(new Set());
        setError('');
    };

    const toggle = (path: string): void => {
        setSelected((current) => {
            const next = new Set(current);
            if (next.has(path)) {
                next.delete(path);
            } else {
                next.add(path);
            }

            return next;
        });
    };

    const importSelection = async (): Promise<void> => {
        if (serverId === '' || selected.size === 0) {
            return;
        }
        setBusy(true);
        setError('');

        let ok = 0;
        let skipped = 0;
        for (const path of selected) {
            const name = path.slice(path.lastIndexOf('/') + 1);
            try {
                const data = await importConfig.mutateAsync({ server_id: Number(serverId), path, format: extensionToFormat(name) });
                onImported(data.file);
                ok += 1;
            } catch {
                skipped += 1;
            }
        }

        setBusy(false);
        setSelected(new Set());

        if (ok === 0) {
            setError(t('admin.editor.import_unsupported_file'));

            return;
        }
        toast.success(skipped > 0 ? t('admin.editor.import_summary', { ok, skipped }) : t('admin.editor.import_done', { ok }));
    };

    return (
        <Dialog
            open={open}
            onClose={close}
            size="lg"
            title={t('admin.editor.import_title')}
            closeLabel={t('common.close')}
            footer={
                <>
                    <Button variant="ghost" onClick={close}>
                        {t('common.close')}
                    </Button>
                    <Button loading={busy} disabled={selected.size === 0} onClick={() => void importSelection()}>
                        {t('admin.editor.import_selected')} ({selected.size})
                    </Button>
                </>
            }
        >
            <div className="ec-stack">
                {error !== '' && (
                    <ul className="ec-error-list">
                        <li>{error}</li>
                    </ul>
                )}

                <div className="ec-field-group">
                    <label>{t('admin.editor.import_server')}</label>
                    <Select value={serverId} onChange={onServerChange} disabled={servers.isLoading}>
                        <option value="">{t('admin.editor.import_server_ph')}</option>
                        {(servers.data ?? []).map((server) => (
                            <option key={server.id} value={String(server.id)}>
                                {server.egg_name !== null ? `${server.name} — ${server.egg_name}` : server.name}
                            </option>
                        ))}
                    </Select>
                </div>

                {serverId !== '' && (
                    <ServerFileBrowser
                        serverId={Number(serverId)}
                        directory={directory}
                        onNavigate={setDirectory}
                        selected={selected}
                        onToggle={toggle}
                    />
                )}
            </div>
        </Dialog>
    );
}
