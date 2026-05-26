import { useCallback, useState } from 'react';
import { serializeExport, parseImport, type ParsedImport, type ThemeExport } from '@/lib/themeStudio/themeTransfer';
import { triggerDownload, pickJsonFile } from '@/lib/themeStudio/downloadJson';

interface ThemeTransferDeps {
    exportPayload: () => ThemeExport | null;
    loadFromExport: (parsed: ParsedImport) => void;
}

interface UseThemeTransferReturn {
    importError: string | null;
    handleExport: () => void;
    handleImport: () => void;
}

/**
 * Wires the Theme Studio's export (download draft as JSON) and import (pick a
 * JSON file → fold into the draft) actions. Kept out of ThemeStudioPage so
 * that page stays under the 300-line cap and the transfer logic is testable
 * in isolation.
 */
export function useThemeTransfer({ exportPayload, loadFromExport }: ThemeTransferDeps): UseThemeTransferReturn {
    const [importError, setImportError] = useState<string | null>(null);

    const handleExport = useCallback(() => {
        const payload = exportPayload();
        if (!payload) return;
        const stamp = new Date().toISOString().slice(0, 10);
        triggerDownload(`peregrine-theme-${stamp}.json`, serializeExport(payload));
    }, [exportPayload]);

    const handleImport = useCallback(() => {
        setImportError(null);
        void pickJsonFile().then((raw) => {
            if (raw === null) return;
            const result = parseImport(raw);
            if (!result.ok) {
                setImportError(result.error);
                return;
            }
            loadFromExport(result.value);
        });
    }, [loadFromExport]);

    return { importError, handleExport, handleImport };
}
