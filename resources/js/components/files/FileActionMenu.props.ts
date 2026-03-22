export interface FileActionMenuProps {
    name: string;
    isFile: boolean;
    isArchive: boolean;
    onRename: () => void;
    onDelete: () => void;
    onCompress: () => void;
    onDecompress: () => void;
}
