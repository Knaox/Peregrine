import { Copy, Search, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { useT } from '../lib/i18n';
import type { ConfigPermissions, ConfigTemplate, ServerState } from '../types';
import { Button } from '../ui/Button';
import { Input, Toggle } from '../ui/inputs';
import { Card, EmptyState } from '../ui/surfaces';
import { BoostSection } from './boost/BoostSection';
import { useBoosts } from './boost/useBoosts';
import { useBoostSelection } from './boost/useBoostSelection';
import type { EditorController } from './controller';
import { CopyDialog } from './copy/CopyDialog';
import { FileCard } from './FileCard';
import { FloatingSaveBar } from './FloatingSaveBar';
import { useConfigSave } from './hooks/useConfigSave';
import { RunningBanner } from './RunningBanner';

export function ConfigEditor({
    serverId,
    templates,
    running,
    permissions,
    state,
    stopping,
    onStop,
}: {
    serverId: number;
    templates: ConfigTemplate[];
    /** Server is running → the editor is read-only (a banner explains it); the
     *  layout is unchanged, edits are intercepted with a "stop the server" message. */
    running: boolean;
    permissions?: ConfigPermissions;
    state: ServerState;
    stopping: boolean;
    onStop: () => void;
}) {
    const { t, lang } = useT();

    // No payload `permissions` → owner/admin (full access). Subusers get the
    // explicit flags from the backend so we can render read-only / hide actions.
    const canWrite = permissions?.write ?? true;
    const canCopy = permissions?.copy ?? true;
    const canBoost = permissions?.boost ?? true;
    // Two read-only reasons, handled differently: no write permission HARD-disables
    // the controls; a running server LOCKS them (still interactive so a tap explains
    // "stop the server first") — but ONLY for templates that require a shutdown to
    // edit (the default). A template with require_shutdown=false stays editable live.
    const hardDisabled = ! canWrite;
    const anyEditableWhileRunning = templates.some((template) => template.require_shutdown === false);
    const anyLocked = running && templates.some((template) => template.require_shutdown !== false);
    const canEdit = canWrite && (! running || anyEditableWhileRunning);

    const editor = useConfigSave({ serverId, templates, running, hardDisabled, canEdit });

    const [search, setSearchState] = useState<string>(() => {
        try {
            return localStorage.getItem(`ec:search:${serverId}`) ?? '';
        } catch {
            return '';
        }
    });
    const setSearch = (value: string): void => {
        setSearchState(value);
        try {
            localStorage.setItem(`ec:search:${serverId}`, value);
        } catch {
            /* localStorage unavailable */
        }
    };
    const [copyOpen, setCopyOpen] = useState(false);
    const boosts = useBoosts(serverId);
    const boost = useBoostSelection(templates, boosts.data, lang, canBoost);

    const controller: EditorController = {
        getValue: editor.getValue,
        isDirty: editor.isDirtyKey,
        isSaved: editor.isSavedKey,
        isInvalid: editor.isInvalidKey,
        disabled: hardDisabled,
        locked: anyLocked,
        search,
        onChange: editor.onChange,
        onReset: editor.onReset,
        boostMode: boost.boostMode,
        isBoostable: boost.isBoostable,
        isBoostSelected: boost.isBoostSelected,
        isBoostLocked: boost.isBoostLocked,
        toggleBoost: boost.toggleBoost,
        isBoostDivide: boost.isBoostDivide,
        toggleDivide: boost.toggleDivide,
        canManageTemplate: permissions?.manage_templates ?? false,
    };

    // Files absent on the server aren't shown at all (a config file only exists
    // once the server has generated it — typically after its first boot). A file
    // that FAILED to read (Wings 5xx / timeout / a throttle or bad path on that
    // one file) is also skipped here so a single unreadable file no longer blanks
    // the whole editor — every config that DID load (200) is still shown.
    const fileCards = templates.flatMap((template) =>
        template.files
            .filter((file) => file.exists !== false && file.read_error !== true)
            .map((file) => ({
                key: `${template.id}:${file.id}`,
                file,
                columns: template.columns,
                templateId: template.id,
                // Per-template lock: only templates requiring a shutdown lock while running.
                locked: running && template.require_shutdown !== false,
            })),
    );

    // Only declare the whole section unreachable when NOTHING could be read AND
    // at least one file errored (a genuine Wings/connectivity problem). If some
    // files loaded, we render those and silently drop the ones that errored.
    const anyReadError = templates.some((template) => template.files.some((file) => file.read_error === true));
    const unreachable = fileCards.length === 0 && anyReadError;

    return (
        <div className="ec-stack">
            <div className="ec-between">
                <div className="ec-row">
                    <span className="ec-icon-box">
                        <SlidersHorizontal size={18} />
                    </span>
                    <div>
                        <h2 className="ec-title">{t('section.title')}</h2>
                        <p className="ec-subtitle">{t('section.subtitle')}</p>
                    </div>
                    {!canWrite && <span className="ec-badge ec-badge-muted">{t('section.read_only')}</span>}
                </div>
                <div className="ec-row">
                    {boost.boostEnabled && (
                        <label className="ec-row" style={{ cursor: 'pointer' }}>
                            <span className="ec-field-desc ec-secondary">{t('boost.mode')}</span>
                            <Toggle checked={boost.boostMode} onChange={boost.setMode} label={t('boost.mode')} />
                        </label>
                    )}
                    {canCopy && (
                        <Button variant="secondary" onClick={() => setCopyOpen(true)}>
                            <Copy size={15} /> {t('copy.button')}
                        </Button>
                    )}
                </div>
            </div>

            {/* Boost management stays accessible even while the server runs. */}
            {boost.boostEnabled && (
                <BoostSection
                    serverId={serverId}
                    boosts={boosts.data ?? []}
                    selectedParams={boost.selectedBoostParams}
                    selectedCount={boost.selectedBoostParams.length}
                />
            )}

            {/* Running server: read-only banner — only when at least one template
                actually locks (require_shutdown). Templates that allow live edits
                don't trigger it. */}
            {anyLocked && <RunningBanner state={state} stopping={stopping} onStop={onStop} />}

            <div className="ec-stack">
                {unreachable ? (
                    <Card>
                        <EmptyState>{t('section.unreachable')}</EmptyState>
                    </Card>
                ) : (
                    <>
                        <div className="ec-search">
                            <span className="ec-search-icon">
                                <Search size={14} />
                            </span>
                            <Input value={search} placeholder={t('section.search')} onChange={(event) => setSearch(event.target.value)} />
                        </div>

                        {fileCards.length === 0 ? (
                            <Card>
                                <EmptyState>{t('section.no_files_yet')}</EmptyState>
                            </Card>
                        ) : (
                            fileCards.map(({ key, file, columns, templateId, locked }) => (
                                <FileCard key={key} file={file} controller={controller} serverId={serverId} templateId={templateId} columns={columns} locked={locked} />
                            ))
                        )}
                    </>
                )}
            </div>

            {/* Own save bar ONLY when the host doesn't provide the unified one;
                otherwise the host's GlobalSaveBar drives doSave via the bridge. */}
            {!editor.useHostBar && (editor.isDirty || editor.justSaved) && canEdit && (
                <FloatingSaveBar saving={editor.saving} saved={editor.justSaved} onSave={editor.doSave} />
            )}

            <CopyDialog open={copyOpen} onClose={() => setCopyOpen(false)} serverId={serverId} templates={templates} />
        </div>
    );
}
