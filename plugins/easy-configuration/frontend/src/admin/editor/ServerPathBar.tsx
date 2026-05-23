import { ChevronRight, CornerLeftUp } from 'lucide-react';
import { useT } from '../../lib/i18n';
import { IconButton } from '../../ui/Button';

interface ServerPathBarProps {
    directory: string;
    onNavigate: (directory: string) => void;
}

/** Breadcrumb + "up" navigation for the server file browser. Mirrors the core
 * file manager's path bar: clicking a segment jumps to that prefix. */
export function ServerPathBar({ directory, onNavigate }: ServerPathBarProps) {
    const { t } = useT();
    const segments = directory.split('/').filter(Boolean);
    const atRoot = segments.length === 0;
    const buildPath = (index: number): string => `/${segments.slice(0, index + 1).join('/')}`;

    return (
        <div className="ec-row ec-pathbar">
            <IconButton
                label={t('admin.editor.import_up')}
                disabled={atRoot}
                onClick={() => onNavigate(atRoot ? '/' : `/${segments.slice(0, -1).join('/')}`)}
            >
                <CornerLeftUp size={15} />
            </IconButton>
            <button type="button" className="ec-crumb" onClick={() => onNavigate('/')}>
                /
            </button>
            {segments.map((segment, index) => (
                <span key={`${segment}-${index}`} className="ec-row" style={{ gap: '0.2rem' }}>
                    <ChevronRight size={13} className="ec-muted" />
                    <button type="button" className="ec-crumb" onClick={() => onNavigate(buildPath(index))}>
                        {segment}
                    </button>
                </span>
            ))}
        </div>
    );
}
