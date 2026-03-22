import { useState, useCallback } from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { fetchFileContent, writeFile } from '@/services/fileApi';

export function useFileEditor() {
    const { t } = useTranslation();
    const queryClient = useQueryClient();

    const [editingFile, setEditingFile] = useState<string | null>(null);
    const [content, setContent] = useState('');
    const [originalContent, setOriginalContent] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    const isDirty = content !== originalContent;

    const saveMutation = useMutation({
        mutationFn: ({ serverId, filePath, fileContent }: {
            serverId: number;
            filePath: string;
            fileContent: string;
        }) => writeFile(serverId, filePath, fileContent),
        onSuccess: () => {
            setOriginalContent(content);
            queryClient.invalidateQueries({ queryKey: ['servers'] });
        },
    });

    const openFile = useCallback(async (serverId: number, filePath: string) => {
        setIsLoading(true);
        try {
            const fileContent = await fetchFileContent(serverId, filePath);
            setEditingFile(filePath);
            setContent(fileContent);
            setOriginalContent(fileContent);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const closeFile = useCallback(() => {
        if (isDirty) {
            const confirmed = window.confirm(t('servers.files.unsaved_changes'));
            if (!confirmed) return;
        }
        setEditingFile(null);
        setContent('');
        setOriginalContent('');
    }, [isDirty, t]);

    const updateContent = useCallback((newContent: string) => {
        setContent(newContent);
    }, []);

    const saveFile = useCallback(async (serverId: number) => {
        if (!editingFile) return;
        await saveMutation.mutateAsync({
            serverId,
            filePath: editingFile,
            fileContent: content,
        });
    }, [editingFile, content, saveMutation]);

    return {
        editingFile,
        content,
        isDirty,
        isLoading,
        isSaving: saveMutation.isPending,
        openFile,
        closeFile,
        updateContent,
        saveFile,
    };
}
