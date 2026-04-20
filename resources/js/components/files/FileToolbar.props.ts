export interface FileToolbarProps {
    onNewFile: () => void;
    onNewFolder: () => void;
    onRefresh: () => void;
    onPull?: () => void;
    canCreate?: boolean;
}
