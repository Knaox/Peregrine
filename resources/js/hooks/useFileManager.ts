import { useState, useCallback } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchFiles } from '@/services/fileApi';
import type { FileEntry } from '@/types/FileEntry';

export function useFileManager(serverId: number) {
    const [currentDirectory, setCurrentDirectory] = useState('/');
    const queryClient = useQueryClient();

    const { data: files, isLoading } = useQuery({
        queryKey: ['servers', serverId, 'files', currentDirectory],
        queryFn: () => fetchFiles(serverId, currentDirectory),
        staleTime: 15_000,
        enabled: serverId > 0,
    });

    const navigateTo = useCallback((dir: string) => {
        setCurrentDirectory(dir);
    }, []);

    const goUp = useCallback(() => {
        if (currentDirectory === '/') return;
        const parts = currentDirectory.split('/').filter(Boolean);
        parts.pop();
        setCurrentDirectory('/' + parts.join('/'));
    }, [currentDirectory]);

    const refresh = useCallback(() => {
        queryClient.invalidateQueries({
            queryKey: ['servers', serverId, 'files', currentDirectory],
        });
    }, [queryClient, serverId, currentDirectory]);

    const sortedFiles: FileEntry[] = [...(files ?? [])].sort((a, b) => {
        if (a.is_directory && !b.is_directory) return -1;
        if (!a.is_directory && b.is_directory) return 1;
        return a.name.localeCompare(b.name);
    });

    return {
        files: sortedFiles,
        isLoading,
        currentDirectory,
        navigateTo,
        goUp,
        refresh,
    };
}
