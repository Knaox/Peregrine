import { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useMutation } from '@tanstack/react-query';
import { m } from 'motion/react';
import { useFileManager } from '@/hooks/useFileManager';
import { useFileEditor } from '@/hooks/useFileEditor';
import { useFileUpload } from '@/hooks/useFileUpload';
import {
    renameFiles,
    deleteFiles,
    compressFiles,
    decompressFile,
    writeFile,
    createFolder,
} from '@/services/fileApi';
import { FileToolbar } from '@/components/files/FileToolbar';
import { FileBreadcrumb } from '@/components/files/FileBreadcrumb';
import { FileList } from '@/components/files/FileList';
import { FileEditor } from '@/components/files/FileEditor';

export function ServerFilesPage() {
    const { t } = useTranslation();
    const { id } = useParams<{ id: string }>();
    const serverId = Number(id);

    const {
        files,
        isLoading,
        currentDirectory,
        navigateTo,
        refresh,
    } = useFileManager(serverId);

    const editor = useFileEditor();
    const { isUploading, progress, error: uploadError, handleDrop } = useFileUpload(serverId);
    const [isDragOver, setIsDragOver] = useState(false);

    const renameMutation = useMutation({
        mutationFn: (params: { from: string; to: string }) =>
            renameFiles(serverId, currentDirectory, [params]),
        onSuccess: refresh,
    });

    const deleteMutation = useMutation({
        mutationFn: (name: string) =>
            deleteFiles(serverId, currentDirectory, [name]),
        onSuccess: refresh,
    });

    const compressMutation = useMutation({
        mutationFn: (name: string) =>
            compressFiles(serverId, currentDirectory, [name]),
        onSuccess: refresh,
    });

    const decompressMutation = useMutation({
        mutationFn: (name: string) =>
            decompressFile(serverId, currentDirectory, name),
        onSuccess: refresh,
    });

    const handleOpenFile = (path: string) => {
        editor.openFile(serverId, path);
    };

    const handleRename = (name: string) => {
        const newName = window.prompt(t('servers.files.create_name'), name);
        if (!newName || newName === name) return;
        renameMutation.mutate({ from: name, to: newName });
    };

    const handleDelete = (name: string) => {
        const confirmed = window.confirm(
            t('servers.files.confirm_delete', { name }),
        );
        if (!confirmed) return;
        deleteMutation.mutate(name);
    };

    const handleCompress = (name: string) => {
        compressMutation.mutate(name);
    };

    const handleDecompress = (name: string) => {
        decompressMutation.mutate(name);
    };

    const handleNewFile = () => {
        const name = window.prompt(t('servers.files.create_name'));
        if (!name) return;
        const filePath = currentDirectory === '/'
            ? `/${name}`
            : `${currentDirectory}/${name}`;
        writeFile(serverId, filePath, '').then(refresh);
    };

    const handleNewFolder = () => {
        const name = window.prompt(t('servers.files.create_name'));
        if (!name) return;
        createFolder(serverId, currentDirectory, name).then(refresh);
    };

    const handleSaveFile = () => {
        editor.saveFile(serverId).then(refresh);
    };

    return (
        <m.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.35, ease: 'easeOut' }}
            className="space-y-4"
        >
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-[var(--color-text-primary)]">
                    {t('servers.files.title')}
                </h2>
                <FileToolbar
                    onNewFile={handleNewFile}
                    onNewFolder={handleNewFolder}
                    onRefresh={refresh}
                />
            </div>

            <FileBreadcrumb
                currentDirectory={currentDirectory}
                onNavigate={navigateTo}
            />

            <div
                className="relative rounded-[var(--radius-lg)] border border-[var(--color-border)] bg-[var(--color-surface)] p-4 transition-all"
                onDragOver={(e) => { e.preventDefault(); setIsDragOver(true); }}
                onDragLeave={() => setIsDragOver(false)}
                onDrop={(e) => { setIsDragOver(false); void handleDrop(e, currentDirectory); }}
            >
                {isDragOver && (
                    <div className="absolute inset-0 z-10 flex items-center justify-center rounded-[var(--radius-lg)] border-2 border-dashed border-[var(--color-primary)] bg-[var(--color-primary)]/5 backdrop-blur-sm">
                        <div className="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" className="mx-auto mb-2 h-10 w-10 text-[var(--color-primary)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={1.5}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                            </svg>
                            <p className="text-[var(--color-primary)] font-medium">{t('servers.files.drop_here')}</p>
                        </div>
                    </div>
                )}

                {isUploading && (
                    <div className="mb-3 rounded-[var(--radius)] bg-[var(--color-primary)]/10 px-3 py-2 text-sm text-[var(--color-primary)]">
                        {progress || t('servers.files.uploading')}
                    </div>
                )}

                {uploadError && (
                    <div className="mb-3 rounded-[var(--radius)] bg-red-500/10 px-3 py-2 text-sm text-red-500">
                        {t('servers.files.upload_error')}: {uploadError}
                    </div>
                )}

                <FileList
                    files={files}
                    currentDirectory={currentDirectory}
                    isLoading={isLoading}
                    onNavigate={navigateTo}
                    onOpenFile={handleOpenFile}
                    onRename={handleRename}
                    onDelete={handleDelete}
                    onCompress={handleCompress}
                    onDecompress={handleDecompress}
                />
            </div>

            {editor.editingFile && (
                <FileEditor
                    filePath={editor.editingFile}
                    content={editor.content}
                    isDirty={editor.isDirty}
                    isSaving={editor.isSaving}
                    onContentChange={editor.updateContent}
                    onSave={handleSaveFile}
                    onClose={editor.closeFile}
                />
            )}
        </m.div>
    );
}
