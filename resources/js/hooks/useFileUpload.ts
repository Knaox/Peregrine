import { useCallback } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { fetchUploadUrl, uploadFilesToWings, createFolder } from '@/services/fileApi';
import { useUploadStore } from '@/stores/uploadStore';

export function useFileUpload(serverId: number) {
    const queryClient = useQueryClient();
    // Subscribe to the global store so the progress survives navigating away
    // from the Files page (the upload keeps running in the background).
    const isUploading = useUploadStore((s) => s.isUploading);
    const percent = useUploadStore((s) => s.percent);
    const error = useUploadStore((s) => s.error);

    const uploadFiles = useCallback(async (
        files: File[],
        currentDirectory: string,
    ) => {
        if (files.length === 0) return;

        const store = useUploadStore.getState();
        // Aggregate % is driven by the sum of file sizes, NOT the per-batch XHR
        // `total` (which includes multipart overhead and resets each batch).
        const totalBytes = files.reduce((sum, f) => sum + f.size, 0) || 1;
        store.start(files.length, currentDirectory);

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

            // Upload files to each directory, aggregating progress across batches
            let completedBytes = 0;
            for (const [dir, dirFileList] of dirFiles) {
                const batchBytes = dirFileList.reduce((sum, f) => sum + f.size, 0);
                await uploadFilesToWings(uploadUrl, dir, dirFileList, (loaded, total) => {
                    const frac = total > 0 ? loaded / total : 0;
                    store.setPercent(((completedBytes + frac * batchBytes) / totalBytes) * 100);
                });
                completedBytes += batchBytes;
            }

            // Invalidate file list query so the new files show up
            void queryClient.invalidateQueries({ queryKey: ['servers', serverId, 'files'] });
            store.finish();
        } catch (err) {
            store.fail(err instanceof Error ? err.message : 'Upload failed');
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

    return { isUploading, percent, error, handleDrop, uploadFiles };
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
