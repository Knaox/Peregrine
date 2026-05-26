export interface ServerResources {
    state: string;
    cpu: number;
    memory_bytes: number;
    disk_bytes: number;
    network_rx: number;
    network_tx: number;
    /** Instantaneous throughput in bytes/sec, derived from the delta between
     *  two consecutive stat samples — the *current* speed, not the cumulative
     *  network_rx/network_tx counters Wings reports since boot. */
    network_rx_rate?: number;
    network_tx_rate?: number;
    uptime: number;
}
