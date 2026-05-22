import { useParams } from 'react-router-dom';
import { useT } from '../lib/i18n';
import { type ApiError } from '../shared';
import { Card, EmptyState, Spinner } from '../ui/surfaces';
import { EditorForm, type Draft } from './editor/EditorForm';
import { useTemplateDetail } from './hooks/useTemplates';

const STARTER_FILES = `[
  {
    "id": "server-properties",
    "path": "server.properties",
    "format": "properties",
    "enabled": true,
    "label": { "en": "Server properties", "fr": "Propriétés serveur" },
    "parameters": {
      "max-players": {
        "display_type": "slider",
        "config": { "min": 1, "max": 100, "step": 1 },
        "label": { "en": "Max players", "fr": "Joueurs max" }
      }
    }
  }
]`;

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
        filesJson: JSON.stringify(def.files ?? [], null, 2),
    };
}

export function TemplateEditorPage() {
    const { t } = useT();
    const { templateId } = useParams<{ templateId: string }>();
    const isNew = templateId === undefined;
    const detail = useTemplateDetail(isNew ? null : (templateId ?? null));

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
