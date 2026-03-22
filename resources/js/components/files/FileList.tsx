import { useTranslation } from 'react-i18next';
import { type FileListProps } from '@/components/files/FileList.props';
import { FileActionMenu } from '@/components/files/FileActionMenu';
import { Spinner } from '@/components/ui/Spinner';
import { formatBytes } from '@/utils/format';

const ARCHIVE_EXTENSIONS = ['.zip', '.tar', '.tar.gz', '.tar.bz2', '.tgz'];

function isArchive(name: string): boolean {
    const lower = name.toLowerCase();
    return ARCHIVE_EXTENSIONS.some((ext) => lower.endsWith(ext));
}

function buildPath(directory: string, name: string): string {
    return directory === '/' ? `/${name}` : `${directory}/${name}`;
}

export function FileList({
    files,
    currentDirectory,
    isLoading,
    onNavigate,
    onOpenFile,
    onRename,
    onDelete,
    onCompress,
    onDecompress,
}: FileListProps) {
    const { t } = useTranslation();

    if (isLoading) {
        return (
            <div className="flex items-center justify-center py-16">
                <Spinner size="lg" />
            </div>
        );
    }

    if (files.length === 0) {
        return (
            <div className="flex items-center justify-center py-16 text-[var(--color-text-muted)] text-sm">
                {t('servers.files.empty')}
            </div>
        );
    }

    return (
        <table className="w-full text-sm">
            <thead>
                <tr className="text-[var(--color-text-muted)] text-left border-b border-[var(--color-border)]">
                    <th className="pb-2 pl-2 w-8" />
                    <th className="pb-2">{t('servers.files.name')}</th>
                    <th className="pb-2 w-28">{t('servers.files.size')}</th>
                    <th className="pb-2 w-44">{t('servers.files.modified')}</th>
                    <th className="pb-2 w-12" />
                </tr>
            </thead>
            <tbody>
                {files.map((file) => {
                    const fullPath = buildPath(currentDirectory, file.name);

                    const handleClick = () => {
                        if (file.is_directory) {
                            onNavigate(fullPath);
                        } else {
                            onOpenFile(fullPath);
                        }
                    };

                    return (
                        <tr
                            key={file.name}
                            className="hover:bg-[var(--color-surface-hover)] transition-colors duration-[var(--transition-fast)] border-b border-[var(--color-border)]/50"
                        >
                            <td className="py-2 pl-2">
                                {file.is_directory ? (
                                    <svg className="w-5 h-5 text-[var(--color-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                                    </svg>
                                ) : (
                                    <svg className="w-5 h-5 text-[var(--color-text-muted)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                )}
                            </td>
                            <td className="py-2">
                                <button
                                    type="button"
                                    onClick={handleClick}
                                    className="text-left text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] transition-colors duration-[var(--transition-fast)]"
                                >
                                    {file.name}
                                </button>
                            </td>
                            <td className="py-2 text-[var(--color-text-muted)]">
                                {file.is_directory
                                    ? '\u2014'
                                    : formatBytes(file.size)}
                            </td>
                            <td className="py-2 text-[var(--color-text-muted)]">
                                {file.modified_at
                                    ? new Date(typeof file.modified_at === 'string' ? file.modified_at : file.modified_at * 1000).toLocaleString()
                                    : '\u2014'}
                            </td>
                            <td className="py-2">
                                <FileActionMenu
                                    name={file.name}
                                    isFile={file.is_file}
                                    isArchive={isArchive(file.name)}
                                    onRename={() => onRename(file.name)}
                                    onDelete={() => onDelete(file.name)}
                                    onCompress={() => onCompress(file.name)}
                                    onDecompress={() => onDecompress(file.name)}
                                />
                            </td>
                        </tr>
                    );
                })}
            </tbody>
        </table>
    );
}
