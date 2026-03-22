export interface FileBreadcrumbProps {
    currentDirectory: string;
    onNavigate: (directory: string) => void;
}
