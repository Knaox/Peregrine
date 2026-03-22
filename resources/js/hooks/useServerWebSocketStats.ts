import { useEffect, useRef, useState, useCallback } from 'react';
import { fetchWebSocketCredentials } from '@/services/serverApi';
import type { ServerResources } from '@/types/ServerResources';

const RECONNECT_MAX_DELAY = 30_000;
const ANSI_REGEX = /\x1b\[[0-9;]*[a-zA-Z]/g;

interface WsEvent {
    event: string;
    args: string[];
}

export function useServerWebSocketStats(serverId: number) {
    const [resources, setResources] = useState<ServerResources | undefined>(undefined);
    const [serverState, setServerState] = useState<string>('offline');
    const [isConnected, setIsConnected] = useState(false);

    const wsRef = useRef<WebSocket | null>(null);
    const reconnectTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const reconnectDelay = useRef(1000);
    const mountedRef = useRef(true);

    const connect = useCallback(async () => {
        if (!mountedRef.current || serverId <= 0) return;

        try {
            const credentials = await fetchWebSocketCredentials(serverId);
            if (!mountedRef.current) return;

            const ws = new WebSocket(credentials.socket);
            wsRef.current = ws;

            ws.onopen = () => {
                ws.send(JSON.stringify({ event: 'auth', args: [credentials.token] }));
            };

            ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data as string) as WsEvent;

                    switch (data.event) {
                        case 'auth success':
                            setIsConnected(true);
                            reconnectDelay.current = 1000;
                            ws.send(JSON.stringify({ event: 'send stats', args: [] }));
                            break;

                        case 'status':
                            if (data.args[0]) {
                                setServerState(data.args[0]);
                            }
                            break;

                        case 'stats':
                            if (data.args[0]) {
                                try {
                                    const stats = JSON.parse(data.args[0]) as Record<string, unknown>;
                                    setResources({
                                        state: (stats.state as string) ?? serverState,
                                        cpu: (stats.cpu_absolute as number) ?? 0,
                                        memory_bytes: (stats.memory_bytes as number) ?? 0,
                                        disk_bytes: (stats.disk_bytes as number) ?? 0,
                                        network_rx: ((stats.network as Record<string, number>)?.rx_bytes) ?? 0,
                                        network_tx: ((stats.network as Record<string, number>)?.tx_bytes) ?? 0,
                                    });
                                    if (stats.state) {
                                        setServerState(stats.state as string);
                                    }
                                } catch {
                                    // ignore malformed stats
                                }
                            }
                            break;

                        case 'token expiring':
                            fetchWebSocketCredentials(serverId)
                                .then((creds) => {
                                    ws.send(JSON.stringify({ event: 'auth', args: [creds.token] }));
                                })
                                .catch(() => {});
                            break;

                        case 'token expired':
                            ws.close();
                            break;
                    }
                } catch {
                    // ignore parse errors
                }
            };

            ws.onclose = () => {
                setIsConnected(false);
                wsRef.current = null;
                if (mountedRef.current) {
                    reconnectTimer.current = setTimeout(() => {
                        reconnectDelay.current = Math.min(reconnectDelay.current * 2, RECONNECT_MAX_DELAY);
                        void connect();
                    }, reconnectDelay.current);
                }
            };

            ws.onerror = () => {
                ws.close();
            };
        } catch {
            if (mountedRef.current) {
                reconnectTimer.current = setTimeout(() => {
                    reconnectDelay.current = Math.min(reconnectDelay.current * 2, RECONNECT_MAX_DELAY);
                    void connect();
                }, reconnectDelay.current);
            }
        }
    }, [serverId]);

    useEffect(() => {
        mountedRef.current = true;
        void connect();

        return () => {
            mountedRef.current = false;
            if (reconnectTimer.current) clearTimeout(reconnectTimer.current);
            if (wsRef.current) wsRef.current.close();
        };
    }, [connect]);

    return { resources, serverState, isConnected };
}
