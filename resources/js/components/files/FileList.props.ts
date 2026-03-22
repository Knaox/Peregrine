import type { FileEntry } from '@/types/FileEntry';

export interface FileListProps {
    files: FileEntry[];
    currentDirectory: string;
    isLoading: boolean;
    onNavigate: (directory: string) => void;
    onOpenFile: (filePath: string) => void;
    onRename: (name: string) => void;
    onDelete: (name: string) => void;
    onCompress: (name: string) => void;
    onDecompress: (name: string) => void;
}
