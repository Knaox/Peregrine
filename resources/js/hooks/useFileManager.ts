import { useCallback } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchFiles } from '@/services/fileApi';
import type { FileEntry } from '@/types/FileEntry';

export function useFileManager(serverId: number) {
    // Current directory lives in the URL (?path=/plugins) so it survives a
    // page reload and the browser Back/Forward buttons walk the history.
    const [searchParams, setSearchParams] = useSearchParams();
    const currentDirectory = searchParams.get('path') || '/';
    const queryClient = useQueryClient();

    const { data: files, isLoading } = useQuery({
        queryKey: ['servers', serverId, 'files', currentDirectory],
        queryFn: () => fetchFiles(serverId, currentDirectory),
        staleTime: 15_000,
        enabled: serverId > 0,
    });

    const navigateTo = useCallback((dir: string) => {
        setSearchParams((prev) => {
            const next = new URLSearchParams(prev);
            // Keep the URL clean at the root — no dangling ?path=/
            if (dir === '/') next.delete('path');
            else next.set('path', dir);
            return next;
        });
    }, [setSearchParams]);

    const goUp = useCallback(() => {
        if (currentDirectory === '/') return;
        const parts = currentDirectory.split('/').filter(Boolean);
        parts.pop();
        navigateTo('/' + parts.join('/'));
    }, [currentDirectory, navigateTo]);

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
