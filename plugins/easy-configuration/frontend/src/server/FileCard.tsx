import { FieldRow } from '../fields/FieldRow';
import { fieldKeyOf } from '../lib/fieldKey';
import { pickLabel, useT } from '../lib/i18n';
import type { ConfigFile, ConfigParam } from '../types';
import { Badge, Card, EmptyState } from '../ui/surfaces';
import type { EditorController } from './controller';
import { SectionGroup } from './SectionGroup';

function groupBySection(params: ConfigParam[]): [string | null, ConfigParam[]][] {
    const groups = new Map<string | null, ConfigParam[]>();
    for (const param of params) {
        const list = groups.get(param.section) ?? [];
        list.push(param);
        groups.set(param.section, list);
    }

    return [...groups.entries()];
}

export function FileCard({ file, controller, serverId }: { file: ConfigFile; controller: EditorController; serverId: number }) {
    const { t, lang } = useT();
    const title = pickLabel(file.label, lang, file.path);

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

    const visible = file.parameters.filter(matches);

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
            />
        );
    };

    return (
        <div className="ec-stack">
            <div className="ec-between">
                <h3 className="ec-title">{title}</h3>
                {!file.exists && <Badge variant="warning">{t('file.missing_badge')}</Badge>}
            </div>

            {!file.exists ? (
                <Card>
                    <EmptyState>{t('file.missing', { path: file.path })}</EmptyState>
                </Card>
            ) : visible.length === 0 ? (
                <Card>
                    <EmptyState>{t('section.no_results')}</EmptyState>
                </Card>
            ) : file.sectioned ? (
                groupBySection(visible).map(([section, params]) => (
                    <SectionGroup
                        key={section ?? '_general'}
                        title={section ?? t('section.general')}
                        storageKey={`ec:col:${serverId}:${file.id}:${section ?? ''}`}
                        count={params.length}
                    >
                        {params.map(renderRow)}
                    </SectionGroup>
                ))
            ) : (
                <div className="ec-section-group">
                    <div className="ec-section-body">{visible.map(renderRow)}</div>
                </div>
            )}
        </div>
    );
}
