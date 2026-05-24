import { useParams } from 'react-router-dom';
import { useT } from '../lib/i18n';
import { type ApiError } from '../shared';
import { Card, EmptyState, Spinner } from '../ui/surfaces';
import { EditorForm } from './editor/EditorForm';
import { blankDraft, draftFrom } from './editor/draft';
import { useExampleTemplate, useTemplateDetail } from './hooks/useTemplates';

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
