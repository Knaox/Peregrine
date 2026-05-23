import { useParams } from 'react-router-dom';
import { useT } from '../lib/i18n';
import { type ApiError } from '../shared';
import { Card, EmptyState, Spinner } from '../ui/surfaces';
import { EditorForm, type Draft } from './editor/EditorForm';
import { useExampleTemplate, useTemplateDetail } from './hooks/useTemplates';

// A new template starts with no files — the admin imports real ones from a
// server (or pastes JSON). A fake starter file used to stay attached even when
// it didn't match the server, which was confusing.
const STARTER_FILES = '[]';

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function str(value: unknown, fallback = ''): string {
    return typeof value === 'string' ? value : fallback;
}

function blankDraft(): Draft {
    return {
        id: '',
        version: '1.0.0',
        nameEn: '',
        nameFr: '',
        descEn: '',
        descFr: '',
        author: '',
        targetEggs: [],
        boostEnabled: false,
        blacklist: '',
        columns: 1,
        filesJson: STARTER_FILES,
    };
}

function draftFrom(id: string, def: Record<string, unknown> | null): Draft {
    if (def === null) {
        return { ...blankDraft(), id };
    }

    const name = isRecord(def.name) ? def.name : {};
    const description = isRecord(def.description) ? def.description : {};
    const boost = isRecord(def.boost) ? def.boost : {};
    const blacklist = Array.isArray(boost.parameter_blacklist) ? boost.parameter_blacklist.map(String) : [];
    const eggs = Array.isArray(def.target_eggs) ? def.target_eggs.filter((v): v is number => typeof v === 'number') : [];

    return {
        id,
        version: str(def.version, '1.0.0'),
        nameEn: str(name.en),
        nameFr: str(name.fr),
        descEn: str(description.en),
        descFr: str(description.fr),
        author: str(def.author),
        targetEggs: eggs,
        boostEnabled: boost.enabled === true,
        blacklist: blacklist.join(', '),
        columns: typeof def.columns === 'number' ? def.columns : 1,
        filesJson: JSON.stringify(def.files ?? [], null, 2),
    };
}

export function TemplateEditorPage({ example = false }: { example?: boolean } = {}) {
    const { t } = useT();
    const { templateId } = useParams<{ templateId: string }>();
    const isNew = !example && templateId === undefined;
    const detail = useTemplateDetail(example || isNew ? null : (templateId ?? null));
    const exampleQuery = useExampleTemplate(example);

    // "Open the example" route: seed the editor with the bundled reference
    // template as a NEW, fully-editable draft (Edit / Visual / Preview tabs).
    if (example) {
        if (exampleQuery.isLoading || exampleQuery.data === undefined) {
            return (
                <div className="ec-page">
                    <div className="ec-row ec-muted">
                        <Spinner /> {t('common.loading')}
                    </div>
                </div>
            );
        }
        const def = exampleQuery.data;
        const id = typeof def.id === 'string' ? def.id : 'example-template';

        return <EditorForm initial={draftFrom(id, def)} isNew />;
    }

    if (isNew) {
        return <EditorForm initial={blankDraft()} isNew />;
    }

    if (detail.isError && (detail.error as unknown as ApiError | null)?.status === 403) {
        return (
            <div className="ec-page">
                <Card>
                    <EmptyState>{t('admin.unauthorized')}</EmptyState>
                </Card>
            </div>
        );
    }

    if (detail.isLoading || detail.data === undefined) {
        return (
            <div className="ec-page">
                <div className="ec-row ec-muted">
                    <Spinner /> {t('common.loading')}
                </div>
            </div>
        );
    }

    return <EditorForm key={detail.data.id} initial={draftFrom(detail.data.id, detail.data.definition)} isNew={false} />;
}
