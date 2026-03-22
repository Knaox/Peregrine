import { useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useMutation } from '@tanstack/react-query';
import { useFileManager } from '@/hooks/useFileManager';
import { useFileEditor } from '@/hooks/useFileEditor';
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
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-white">
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

            <div className="bg-slate-800 rounded-lg border border-slate-700 p-4">
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
        </div>
    );
}
