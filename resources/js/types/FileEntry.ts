export interface FileEntry {
    name: string;
    mode: string;
    size: number;
    is_file: boolean;
    is_symlink: boolean;
    is_directory: boolean;
    modified_at: number;
    mimetype?: string;
}
