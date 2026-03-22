import { useState, useCallback } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { fetchUploadUrl, uploadFilesToWings, createFolder } from '@/services/fileApi';

interface UploadState {
    isUploading: boolean;
    progress: string;
    error: string | null;
}

export function useFileUpload(serverId: number) {
    const [state, setState] = useState<UploadState>({
        isUploading: false,
        progress: '',
        error: null,
    });
    const queryClient = useQueryClient();

    const uploadFiles = useCallback(async (
        files: File[],
        currentDirectory: string,
    ) => {
        if (files.length === 0) return;

        setState({ isUploading: true, progress: `Uploading ${files.length} file(s)...`, error: null });

        try {
            const uploadUrl = await fetchUploadUrl(serverId);

            // Group files by their relative directory path
            const dirFiles = new Map<string, File[]>();
            for (const file of files) {
                const relativePath = (file as File & { webkitRelativePath?: string }).webkitRelativePath || file.name;
                const parts = relativePath.split('/');
                const dir = parts.length > 1
                    ? currentDirectory + '/' + parts.slice(0, -1).join('/')
                    : currentDirectory;

                if (!dirFiles.has(dir)) {
                    dirFiles.set(dir, []);
                }
                dirFiles.get(dir)?.push(file);
            }

            // Create subdirectories first, then upload files
            const dirs = Array.from(dirFiles.keys()).sort();
            for (const dir of dirs) {
                if (dir !== currentDirectory) {
                    const relativeDirPath = dir.replace(currentDirectory + '/', '');
                    const parts = relativeDirPath.split('/');
                    let buildPath = currentDirectory;
                    for (const part of parts) {
                        try {
                            await createFolder(serverId, buildPath, part);
                        } catch {
                            // Directory may already exist
                        }
                        buildPath = buildPath === '/' ? '/' + part : buildPath + '/' + part;
                    }
                }
            }

            // Upload files to each directory
            for (const [dir, dirFileList] of dirFiles) {
                setState(prev => ({ ...prev, progress: `Uploading to ${dir}...` }));
                await uploadFilesToWings(uploadUrl, dir, dirFileList);
            }

            // Invalidate file list query
            void queryClient.invalidateQueries({ queryKey: ['servers', serverId, 'files'] });
            setState({ isUploading: false, progress: '', error: null });
        } catch (err) {
            setState({
                isUploading: false,
                progress: '',
                error: err instanceof Error ? err.message : 'Upload failed',
            });
        }
    }, [serverId, queryClient]);

    const handleDrop = useCallback(async (
        e: React.DragEvent,
        currentDirectory: string,
    ) => {
        e.preventDefault();
        e.stopPropagation();

        const items = e.dataTransfer.items;
        const allFiles: File[] = [];

        // Use webkitGetAsEntry for folder support
        const entries: FileSystemEntry[] = [];
        for (let i = 0; i < items.length; i++) {
            const entry = items[i]?.webkitGetAsEntry?.();
            if (entry) entries.push(entry);
        }

        if (entries.length > 0) {
            await readEntries(entries, allFiles, '');
        } else {
            // Fallback: plain file list
            for (let i = 0; i < e.dataTransfer.files.length; i++) {
                const f = e.dataTransfer.files[i];
                if (f) allFiles.push(f);
            }
        }

        await uploadFiles(allFiles, currentDirectory);
    }, [uploadFiles]);

    return { ...state, handleDrop, uploadFiles };
}

async function readEntries(
    entries: FileSystemEntry[],
    allFiles: File[],
    basePath: string,
): Promise<void> {
    for (const entry of entries) {
        if (entry.isFile) {
            const file = await getFileFromEntry(entry as FileSystemFileEntry);
            Object.defineProperty(file, 'webkitRelativePath', {
                value: basePath ? basePath + '/' + file.name : file.name,
                writable: false,
            });
            allFiles.push(file);
        } else if (entry.isDirectory) {
            const dirReader = (entry as FileSystemDirectoryEntry).createReader();
            const subEntries = await readDirectoryEntries(dirReader);
            const subPath = basePath ? basePath + '/' + entry.name : entry.name;
            await readEntries(subEntries, allFiles, subPath);
        }
    }
}

function getFileFromEntry(entry: FileSystemFileEntry): Promise<File> {
    return new Promise((resolve, reject) => {
        entry.file(resolve, reject);
    });
}

function readDirectoryEntries(reader: FileSystemDirectoryReader): Promise<FileSystemEntry[]> {
    return new Promise((resolve, reject) => {
        const results: FileSystemEntry[] = [];
        const readBatch = () => {
            reader.readEntries((entries) => {
                if (entries.length === 0) {
                    resolve(results);
                } else {
                    results.push(...entries);
                    readBatch();
                }
            }, reject);
        };
        readBatch();
    });
}
