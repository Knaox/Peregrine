import clsx from 'clsx';
import { ChevronDown, Plus } from 'lucide-react';
import { useState } from 'react';
import { FieldRow } from '../fields/FieldRow';
import { fieldKeyOf } from '../lib/fieldKey';
import { pickLabel, useT } from '../lib/i18n';
import type { ConfigFile, ConfigParam } from '../types';
import { Button } from '../ui/Button';
import { Badge, Card, EmptyState } from '../ui/surfaces';
import { AddParameterDialog } from './AddParameterDialog';
import { AnnotateParameterDialog } from './AnnotateParameterDialog';
import { BoostBadge } from './boost/BoostBadge';
import type { EditorController } from './controller';
import { SectionGroup, sectionBodyClass } from './SectionGroup';
import { useCollapsed } from './useCollapsed';

function groupBySection(params: ConfigParam[]): [string | null, ConfigParam[]][] {
    const groups = new Map<string | null, ConfigParam[]>();
    for (const param of params) {
        const list = groups.get(param.section) ?? [];
        list.push(param);
        groups.set(param.section, list);
    }

    return [...groups.entries()];
}

/**
 * Initial open state for a section inside an already-expanded file: sections
 * default OPEN (the file-level toggle is the primary collapse unit), but the
 * template may force a specific section closed via `section_expanded`.
 */
function resolveSectionDefault(file: ConfigFile, section: string | null): boolean {
    if (section !== null && file.section_expanded != null && section in file.section_expanded) {
        return file.section_expanded[section];
    }

    return true;
}

export function FileCard({ file, controller, serverId, templateId, forceCollapsed = false, columns }: { file: ConfigFile; controller: EditorController; serverId: number; templateId: string; forceCollapsed?: boolean; columns?: number }) {
    const { t, lang } = useT();
    const title = pickLabel(file.label, lang, file.path);
    const [addOpen, setAddOpen] = useState(false);
    const [annotateParam, setAnnotateParam] = useState<ConfigParam | null>(null);
    const sections = [...new Set(file.parameters.map((p) => p.section).filter((s): s is string => s !== null))];
    // The whole file is one collapsible unit (click its title). Base = collapsed;
    // the template flips it open via `expanded_by_default`; running forces closed.
    const fileCollapse = useCollapsed(`ec:col:${serverId}:${file.id}:_file`, file.expanded_by_default ?? false, forceCollapsed);

    const query = controller.search.trim().toLowerCase();
    const matches = (param: ConfigParam): boolean => {
        if (query === '') {
            return true;
        }

        return (
            pickLabel(param.label, lang, param.key).toLowerCase().includes(query) ||
            param.key.toLowerCase().includes(query) ||
            (param.section?.toLowerCase().includes(query) ?? false)
        );
    };

    // Env-linked parameters are surfaced in the core "Server configuration"
    // (startup variables) section with a link badge — not here — so they're
    // excluded from the Easy Configuration editor to avoid a duplicate surface.
    const isLinked = (param: ConfigParam): boolean => typeof param.env_var === 'string' && param.env_var !== '';
    const visible = file.parameters.filter((param) => ! isLinked(param) && matches(param));

    const renderRow = (param: ConfigParam) => {
        const key = fieldKeyOf(file.id, param);

        return (
            <FieldRow
                key={key}
                param={param}
                value={controller.getValue(key)}
                dirty={controller.isDirty(key)}
                saved={controller.isSaved(key)}
                invalid={controller.isInvalid(key)}
                disabled={controller.disabled}
                onChange={(value) => controller.onChange(key, param, value)}
                onReset={() => controller.onReset(key, param)}
                boost={param.boost ? <BoostBadge boost={param.boost} /> : undefined}
                boostMode={controller.boostMode}
                boostable={controller.isBoostable(key)}
                boostSelected={controller.isBoostSelected(key)}
                boostLocked={controller.isBoostLocked(key)}
                onToggleBoost={() => controller.toggleBoost(key)}
                boostDivide={controller.isBoostDivide(key)}
                onToggleDivide={() => controller.toggleDivide(key)}
                onAnnotate={controller.canManageTemplate ? () => setAnnotateParam(param) : undefined}
            />
        );
    };

    return (
        <div className="ec-stack">
            {!file.exists ? (
                <>
                    <div className="ec-between">
                        <h3 className="ec-title">{title}</h3>
                        <Badge variant="warning">{t('file.missing_badge')}</Badge>
                    </div>
                    <Card>
                        <EmptyState>{t('file.missing', { path: file.path })}</EmptyState>
                    </Card>
                </>
            ) : (
                <>
                    <button
                        type="button"
                        className={clsx('ec-file-head', !fileCollapse.isOpen && 'ec-section-collapsed')}
                        onClick={fileCollapse.toggle}
                        aria-expanded={fileCollapse.isOpen}
                        disabled={forceCollapsed}
                    >
                        <span className="ec-section-chevron">
                            <ChevronDown size={18} />
                        </span>
                        <span className="ec-title">{title}</span>
                    </button>

                    {fileCollapse.isOpen && (
                        <>
                            {visible.length === 0 ? (
                                <Card>
                                    <EmptyState>{t('section.no_results')}</EmptyState>
                                </Card>
                            ) : file.sectioned ? (
                                groupBySection(visible).map(([section, params]) => (
                                    <SectionGroup
                                        key={section ?? '_general'}
                                        title={section === null ? t('section.general') : pickLabel(file.section_labels?.[section], lang, section)}
                                        storageKey={`ec:col:${serverId}:${file.id}:${section ?? ''}`}
                                        count={params.length}
                                        forceCollapsed={forceCollapsed}
                                        expandedByDefault={resolveSectionDefault(file, section)}
                                        columns={columns}
                                    >
                                        {params.map(renderRow)}
                                    </SectionGroup>
                                ))
                            ) : (
                                <div className="ec-section-group">
                                    <div className={sectionBodyClass(columns)}>{visible.map(renderRow)}</div>
                                </div>
                            )}

                            {!controller.disabled && (
                                <div>
                                    <Button variant="ghost" size="sm" onClick={() => setAddOpen(true)}>
                                        <Plus size={14} /> {t('add_param.button')}
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </>
            )}

            <AddParameterDialog open={addOpen} onClose={() => setAddOpen(false)} serverId={serverId} fileId={file.id} sections={sections} params={file.parameters} />

            {annotateParam && (
                <AnnotateParameterDialog
                    serverId={serverId}
                    templateId={templateId}
                    fileId={file.id}
                    param={annotateParam}
                    onClose={() => setAnnotateParam(null)}
                />
            )}
        </div>
    );
}
