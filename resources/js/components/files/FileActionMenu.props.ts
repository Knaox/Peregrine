export interface FileActionMenuProps {
    name: string;
    isFile: boolean;
    isArchive: boolean;
    onRename: () => void;
    onDelete: () => void;
    onCompress: () => void;
    onDecompress: () => void;
    onChmod: () => void;
    /**
     * Triggered when the user clicks "Download". Wired up at the page level :
     * the page resolves a one-shot signed URL via `getFileDownloadUrl()` and
     * opens it in a new tab. Skipped for folders (`isFile === false`) and
     * when the user lacks the `file.read` permission.
     */
    onDownload?: () => void;
    /** Optional permission flags; undefined = allowed (backward compatible). */
    canUpdate?: boolean;
    canDelete?: boolean;
    canArchive?: boolean;
    canDownload?: boolean;
}
