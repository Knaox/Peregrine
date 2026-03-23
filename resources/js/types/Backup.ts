export interface Backup {
    uuid: string;
    name: string;
    ignored_files: string[];
    checksum: string | null;
    bytes: number;
    created_at: string;
    completed_at: string | null;
    is_locked: boolean;
    is_successful: boolean;
}
