export interface Allocation {
    id: number;
    ip: string;
    ip_alias: string | null;
    port: number;
    alias: string | null;
    notes: string | null;
    is_default: boolean;
}
