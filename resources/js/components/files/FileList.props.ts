import type { FileEntry } from '@/types/FileEntry';

export interface FileListProps {
    files: FileEntry[];
    currentDirectory: string;
    isLoading: boolean;
    selectedFiles: Set<string>;
    onToggleSelect: (name: string) => void;
    onToggleSelectAll: () => void;
    onNavigate: (directory: string) => void;
    onOpenFile: (filePath: string) => void;
    onRename: (name: string) => void;
    onDelete: (name: string) => void;
    onCompress: (name: string) => void;
    onDecompress: (name: string) => void;
    onChmod: (name: string) => void;
    canUpdate?: boolean;
    canDelete?: boolean;
    canArchive?: boolean;
}
