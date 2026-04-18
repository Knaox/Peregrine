import { useCallback, useRef, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useMutation } from '@tanstack/react-query';
import { AnimatePresence, m } from 'motion/react';
import { useFileManager } from '@/hooks/useFileManager';
import { useFileEditor } from '@/hooks/useFileEditor';
import { useFileUpload } from '@/hooks/useFileUpload';
import {
    renameFiles, deleteFiles, compressFiles, decompressFile, writeFile, createFolder,
} from '@/services/fileApi';
import { FileToolbar } from '@/components/files/FileToolbar';
import { FileBreadcrumb } from '@/components/files/FileBreadcrumb';
import { FileList } from '@/components/files/FileList';
import { FileEditor } from '@/components/files/FileEditor';
import { FileBulkBar } from '@/components/files/FileBulkBar';

export function ServerFilesPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);

    const { files, isLoading, currentDirectory, navigateTo, refresh } = useFileManager(serverId);
    const editor = useFileEditor();
    const { isUploading, progress, error: uploadError, handleDrop } = useFileUpload(serverId);
    const [isDragOver, setIsDragOver] = useState(false);
    const dragCounter = useRef(0);
    const [selectedFiles, setSelectedFiles] = useState<Set<string>>(new Set());

    const toggleSelect = useCallback((name: string) => {
        setSelectedFiles((prev) => {
            const next = new Set(prev);
            next.has(name) ? next.delete(name) : next.add(name);
            return next;
        });
    }, []);
    const toggleSelectAll = useCallback(() => {
        setSelectedFiles((prev) => prev.size === files.length ? new Set() : new Set(files.map((f) => f.name)));
    }, [files]);
    const deselectAll = useCallback(() => setSelectedFiles(new Set()), []);

    const handleNavigate = useCallback((dir: string) => { deselectAll(); navigateTo(dir); }, [navigateTo, deselectAll]);

    const renameMutation = useMutation({
        mutationFn: (params: { from: string; to: string }) => renameFiles(serverId, currentDirectory, [params]),
        onSuccess: refresh,
    });
    const deleteMutation = useMutation({
        mutationFn: (names: string[]) => deleteFiles(serverId, currentDirectory, names),
        onSuccess: () => { deselectAll(); refresh(); },
    });
    const compressMutation = useMutation({
        mutationFn: (names: string[]) => compressFiles(serverId, currentDirectory, names),
        onSuccess: () => { deselectAll(); refresh(); },
    });
    const decompressMutation = useMutation({
        mutationFn: (name: string) => decompressFile(serverId, currentDirectory, name),
        onSuccess: refresh,
    });

    const handleOpenFile = (path: string) => { editor.openFile(serverId, path); };
    const handleRename = (name: string) => {
        const newName = window.prompt(t('servers.files.create_name'), name);
        if (!newName || newName === name) return;
        renameMutation.mutate({ from: name, to: newName });
    };
    const handleDelete = (name: string) => {
        if (window.confirm(t('servers.files.confirm_delete', { name }))) deleteMutation.mutate([name]);
    };
    const handleCompress = (name: string) => { compressMutation.mutate([name]); };
    const handleDecompress = (name: string) => { decompressMutation.mutate(name); };
    const handleBulkDelete = () => {
        const names = Array.from(selectedFiles);
        if (window.confirm(t('servers.files.confirm_delete', { name: `${names.length} files` }))) deleteMutation.mutate(names);
    };
    const handleBulkCompress = () => { compressMutation.mutate(Array.from(selectedFiles)); };
    const handleNewFile = () => {
        const name = window.prompt(t('servers.files.create_name'));
        if (!name) return;
        writeFile(serverId, currentDirectory === '/' ? `/${name}` : `${currentDirectory}/${name}`, '').then(refresh);
    };
    const handleNewFolder = () => {
        const name = window.prompt(t('servers.files.create_name'));
        if (!name) return;
        createFolder(serverId, currentDirectory, name).then(refresh);
    };
    const handleSaveFile = () => { editor.saveFile(serverId).then(refresh); };

    return (
        <m.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }} className="space-y-4 pb-16">
            {/* Header */}
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-[var(--color-text-primary)]">{t('servers.files.title')}</h2>
                <FileToolbar onNewFile={handleNewFile} onNewFolder={handleNewFolder} onRefresh={refresh} />
            </div>

            {/* Breadcrumb */}
            <div className="glass-card-enhanced rounded-[var(--radius-lg)] px-3 py-1">
                <FileBreadcrumb currentDirectory={currentDirectory} onNavigate={handleNavigate} />
            </div>

            {/* File list container */}
            <div
                className="relative glass-card-enhanced rounded-[var(--radius-lg)] p-3 sm:p-4 transition-all min-h-[300px]"
                onDragEnter={(e) => { e.preventDefault(); dragCounter.current++; setIsDragOver(true); }}
                onDragOver={(e) => { e.preventDefault(); }}
                onDragLeave={() => { dragCounter.current--; if (dragCounter.current <= 0) { dragCounter.current = 0; setIsDragOver(false); } }}
                onDrop={(e) => { dragCounter.current = 0; setIsDragOver(false); void handleDrop(e, currentDirectory); }}
            >
                {/* Drag overlay */}
                <AnimatePresence>
                    {isDragOver && (
                        <m.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="absolute inset-0 z-10 flex items-center justify-center rounded-[var(--radius-lg)] border-2 border-dashed border-[var(--color-primary)] bg-[var(--color-primary)]/5 backdrop-blur-sm"
                        >
                            <div className="text-center">
                                <svg className="mx-auto mb-2 h-10 w-10 text-[var(--color-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                    <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                                <p className="text-[var(--color-primary)] font-medium">{t('servers.files.drop_here')}</p>
                            </div>
                        </m.div>
                    )}
                </AnimatePresence>

                {/* Status messages */}
                {isUploading && <div className="mb-3 rounded-[var(--radius)] bg-[var(--color-primary)]/10 px-3 py-2 text-sm text-[var(--color-primary)]">{progress || t('servers.files.uploading')}</div>}
                {uploadError && <div className="mb-3 rounded-[var(--radius)] bg-[var(--color-danger)]/10 px-3 py-2 text-sm text-[var(--color-danger)]">{t('servers.files.upload_error')}: {uploadError}</div>}
                {editor.error && <div className="mb-3 rounded-[var(--radius)] bg-[var(--color-danger)]/10 px-3 py-2 text-sm text-[var(--color-danger)]">{editor.error}</div>}

                <FileList
                    files={files} currentDirectory={currentDirectory} isLoading={isLoading}
                    selectedFiles={selectedFiles} onToggleSelect={toggleSelect} onToggleSelectAll={toggleSelectAll}
                    onNavigate={handleNavigate} onOpenFile={handleOpenFile}
                    onRename={handleRename} onDelete={handleDelete} onCompress={handleCompress} onDecompress={handleDecompress}
                />
            </div>

            {/* Editor modal */}
            <AnimatePresence>
                {editor.editingFile && (
                    <FileEditor filePath={editor.editingFile} content={editor.content} isDirty={editor.isDirty} isSaving={editor.isSaving}
                        onContentChange={editor.updateContent} onSave={handleSaveFile} onClose={editor.closeFile} />
                )}
            </AnimatePresence>

            <FileBulkBar
                selectedCount={selectedFiles.size} onDelete={handleBulkDelete} onCompress={handleBulkCompress}
                onDeselectAll={deselectAll} isDeleting={deleteMutation.isPending} isCompressing={compressMutation.isPending}
            />
        </m.div>
    );
}
