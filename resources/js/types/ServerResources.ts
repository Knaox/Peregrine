export interface ServerResources {
    state: string;
    cpu: number;
    memory_bytes: number;
    disk_bytes: number;
    network_rx: number;
    network_tx: number;
    uptime: number;
}
