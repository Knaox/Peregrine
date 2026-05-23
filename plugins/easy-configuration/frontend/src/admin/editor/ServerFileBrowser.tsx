import clsx from 'clsx';
import { CheckSquare, ChevronRight, FileText, Folder, Square } from 'lucide-react';
import { useT } from '../../lib/i18n';
import { extensionToFormat, formatBytes } from '../../lib/format';
import type { ServerFileEntry } from '../../types';
import { EmptyState, Spinner } from '../../ui/surfaces';
import { useServerFiles } from '../hooks/useServerFiles';
import { ServerPathBar } from './ServerPathBar';

interface ServerFileBrowserProps {
    serverId: number;
    directory: string;
    onNavigate: (directory: string) => void;
    selected: Set<string>;
    onToggle: (path: string) => void;
}

function join(directory: string, name: string): string {
    return directory === '/' ? `/${name}` : `${directory}/${name}`;
}

/** Sort directories first, then by name (case-insensitive) — mirrors core. */
function sortEntries(entries: ServerFileEntry[]): ServerFileEntry[] {
    return [...entries].sort((a, b) => {
        if (a.is_directory !== b.is_directory) {
            return a.is_directory ? -1 : 1;
        }

        return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
    });
}

/** Browses one server directory: folders descend on click, config files carry a
 * checkbox so several can be selected (selection lives in the parent and
 * persists across navigation). */
export function ServerFileBrowser({ serverId, directory, onNavigate, selected, onToggle }: ServerFileBrowserProps) {
    const { t } = useT();
    const query = useServerFiles(serverId, directory);

    return (
        <div className="ec-stack">
            <ServerPathBar directory={directory} onNavigate={onNavigate} />
            <p className="ec-field-desc ec-muted">{t('admin.editor.import_pick_hint')}</p>

            {query.isLoading ? (
                <div className="ec-row ec-muted">
                    <Spinner /> {t('common.loading')}
                </div>
            ) : query.isError ? (
                <ul className="ec-error-list">
                    <li>{t('admin.editor.import_browse_failed')}</li>
                </ul>
            ) : (query.data ?? []).length === 0 ? (
                <EmptyState>{t('admin.editor.import_empty_dir')}</EmptyState>
            ) : (
                <div className="ec-egg-list">
                    {sortEntries(query.data ?? []).map((entry) =>
                        entry.is_directory ? (
                            <button
                                key={entry.name}
                                type="button"
                                className="ec-server-row"
                                onClick={() => onNavigate(join(directory, entry.name))}
                            >
                                <Folder size={18} />
                                <span className="ec-grow ec-truncate">{entry.name}</span>
                                <ChevronRight size={16} className="ec-muted" />
                            </button>
                        ) : (
                            <FileRow
                                key={entry.name}
                                entry={entry}
                                checked={selected.has(join(directory, entry.name))}
                                onToggle={() => onToggle(join(directory, entry.name))}
                            />
                        ),
                    )}
                </div>
            )}
        </div>
    );
}

function FileRow({ entry, checked, onToggle }: { entry: ServerFileEntry; checked: boolean; onToggle: () => void }) {
    const format = extensionToFormat(entry.name);
    const size = formatBytes(entry.size);

    return (
        <button type="button" className={clsx('ec-server-row', checked && 'ec-server-row-on')} onClick={onToggle}>
            {checked ? <CheckSquare size={18} /> : <Square size={18} className="ec-muted" />}
            <FileText size={16} className="ec-muted" />
            <span className="ec-grow ec-truncate">{entry.name}</span>
            {format !== undefined && <span className="ec-muted">{format}</span>}
            {size !== '' && <span className="ec-muted">{size}</span>}
        </button>
    );
}
