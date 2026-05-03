import { useTranslation } from 'react-i18next';
import { m, AnimatePresence } from 'motion/react';
import clsx from 'clsx';
import { type FileListProps } from '@/components/files/FileList.props';
import { FileActionMenu } from '@/components/files/FileActionMenu';
import { Spinner } from '@/components/ui/Spinner';
import { formatBytes } from '@/utils/format';

const ARCHIVE_EXTENSIONS = ['.zip', '.tar', '.tar.gz', '.tar.bz2', '.tgz'];

function isArchive(name: string): boolean {
    return ARCHIVE_EXTENSIONS.some((ext) => name.toLowerCase().endsWith(ext));
}

function buildPath(directory: string, name: string): string {
    return directory === '/' ? `/${name}` : `${directory}/${name}`;
}

function getFileIcon(name: string, isDir: boolean): React.ReactNode {
    if (isDir) return (
        <svg className="w-5 h-5 text-[var(--color-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
        </svg>
    );
    const ext = name.split('.').pop()?.toLowerCase() ?? '';
    const configExts = ['yml', 'yaml', 'json', 'toml', 'properties', 'cfg', 'ini', 'conf'];
    const codeExts = ['js', 'ts', 'jsx', 'tsx', 'py', 'java', 'php', 'rb', 'go', 'rs', 'sh', 'bash'];
    const color = configExts.includes(ext) ? 'var(--color-warning)' : codeExts.includes(ext) ? 'var(--color-info)' : 'var(--color-text-muted)';
    return (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke={color} strokeWidth={1.5}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
        </svg>
    );
}

export function FileList({
    files, currentDirectory, isLoading,
    selectedFiles, onToggleSelect, onToggleSelectAll,
    onNavigate, onOpenFile, onRename, onDelete, onCompress, onDecompress, onChmod, onDownload,
    canUpdate = true, canDelete = true, canArchive = true, canDownload = true,
}: FileListProps) {
    const { t } = useTranslation();
    const allSelected = files.length > 0 && selectedFiles.size === files.length;

    if (isLoading) return <div className="flex items-center justify-center py-16"><Spinner size="lg" /></div>;
    if (files.length === 0) return (
        <div className="flex flex-col items-center justify-center py-16 gap-2">
            <svg className="w-12 h-12 text-[var(--color-text-muted)] opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1}>
                <path strokeLinecap="round" strokeLinejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
            </svg>
            <p className="text-[var(--color-text-muted)] text-sm">{t('servers.files.empty')}</p>
        </div>
    );

    return (
        <div className="overflow-x-auto -mx-3 sm:mx-0">
            <div className="inline-block min-w-full px-3 sm:px-0">
            {/* Header row */}
            <div className="flex items-center gap-3 px-3 py-2 text-xs font-medium text-[var(--color-text-muted)] border-b border-[var(--color-border)]/50">
                <div className="w-6 flex-shrink-0">
                    <input type="checkbox" checked={allSelected} onChange={onToggleSelectAll} className="rounded cursor-pointer" />
                </div>
                <div className="w-6 flex-shrink-0" />
                <div className="flex-1 min-w-0">{t('servers.files.name')}</div>
                <div className="w-24 text-right hidden sm:block">{t('servers.files.size')}</div>
                <div className="w-40 text-right hidden md:block">{t('servers.files.modified')}</div>
                <div className="w-8" />
            </div>

            {/* File rows */}
            <AnimatePresence mode="popLayout">
                {files.map((file, i) => {
                    const fullPath = buildPath(currentDirectory, file.name);
                    const isSelected = selectedFiles.has(file.name);

                    const handleClick = () => {
                        if (file.is_directory) onNavigate(fullPath);
                        else onOpenFile(fullPath);
                    };

                    return (
                        <m.div
                            key={file.name}
                            initial={{ opacity: 0, x: -8 }}
                            animate={{ opacity: 1, x: 0 }}
                            exit={{ opacity: 0, x: 8 }}
                            transition={{ delay: i * 0.015, duration: 0.2 }}
                            className={clsx(
                                'flex items-center gap-3 px-3 py-2 rounded-[var(--radius-sm)] group',
                                'transition-colors duration-150 cursor-pointer',
                                isSelected
                                    ? 'bg-[var(--color-primary)]/8 ring-1 ring-[var(--color-primary)]/20'
                                    : 'hover:bg-[var(--color-surface-hover)]',
                            )}
                            onClick={handleClick}
                            onDoubleClick={handleClick}
                        >
                            {/* Checkbox */}
                            <div className="w-6 flex-shrink-0" onClick={(e) => e.stopPropagation()}>
                                <input type="checkbox" checked={isSelected}
                                    onChange={() => onToggleSelect(file.name)}
                                    className="rounded cursor-pointer" />
                            </div>

                            {/* Icon */}
                            <div className="w-6 flex-shrink-0">
                                {getFileIcon(file.name, file.is_directory)}
                            </div>

                            {/* Name */}
                            <div className="flex-1 min-w-0">
                                <span className={clsx(
                                    'text-sm truncate block',
                                    file.is_directory
                                        ? 'font-medium text-[var(--color-text-primary)]'
                                        : 'text-[var(--color-text-secondary)] group-hover:text-[var(--color-text-primary)]',
                                )}>
                                    {file.name}
                                </span>
                            </div>

                            {/* Size */}
                            <div className="w-24 text-right text-xs text-[var(--color-text-muted)] hidden sm:block font-mono">
                                {file.is_directory ? '\u2014' : formatBytes(file.size)}
                            </div>

                            {/* Modified */}
                            <div className="w-40 text-right text-xs text-[var(--color-text-muted)] hidden md:block">
                                {file.modified_at
                                    ? new Date(typeof file.modified_at === 'string' ? file.modified_at : file.modified_at * 1000).toLocaleString()
                                    : '\u2014'}
                            </div>

                            {/* Actions */}
                            <div className="w-8 flex-shrink-0" onClick={(e) => e.stopPropagation()}>
                                <FileActionMenu name={file.name} isFile={file.is_file} isArchive={isArchive(file.name)}
                                    onRename={() => onRename(file.name)} onDelete={() => onDelete(file.name)}
                                    onCompress={() => onCompress(file.name)} onDecompress={() => onDecompress(file.name)}
                                    onChmod={() => onChmod(file.name)}
                                    onDownload={onDownload ? () => onDownload(file.name) : undefined}
                                    canUpdate={canUpdate} canDelete={canDelete} canArchive={canArchive}
                                    canDownload={canDownload} />
                            </div>
                        </m.div>
                    );
                })}
            </AnimatePresence>
            </div>
        </div>
    );
}
