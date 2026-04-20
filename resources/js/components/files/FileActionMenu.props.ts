export interface FileActionMenuProps {
    name: string;
    isFile: boolean;
    isArchive: boolean;
    onRename: () => void;
    onDelete: () => void;
    onCompress: () => void;
    onDecompress: () => void;
    onChmod: () => void;
    /** Optional permission flags; undefined = allowed (backward compatible). */
    canUpdate?: boolean;
    canDelete?: boolean;
    canArchive?: boolean;
}
