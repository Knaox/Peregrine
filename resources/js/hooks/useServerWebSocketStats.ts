import { useEffect, useRef, useState, useCallback } from 'react';
import { fetchWebSocketCredentials } from '@/services/serverApi';
import { useWsRetryState, type WsFailure } from '@/hooks/useWsRetryState';
import type { ServerResources } from '@/types/ServerResources';

interface WsEvent {
    event: string;
    args: string[];
}

export function useServerWebSocketStats(serverId: number) {
    const [resources, setResources] = useState<ServerResources | undefined>(undefined);
    const [serverState, setServerState] = useState<string>('offline');
    const [isConnected, setIsConnected] = useState(false);
    const [isGaveUp, setIsGaveUp] = useState(false);

    const wsRef = useRef<WebSocket | null>(null);
    const reconnectTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const mountedRef = useRef(true);
    const retry = useWsRetryState();

    const scheduleReconnect = useCallback((signal: WsFailure) => {
        if (retry.shouldGiveUp(signal)) {
            setIsGaveUp(true);
            return;
        }
        if (reconnectTimer.current) clearTimeout(reconnectTimer.current);
        const delay = retry.nextDelay();
        reconnectTimer.current = setTimeout(() => {
            void connect();
        }, delay);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const connect = useCallback(async () => {
        if (!mountedRef.current || serverId <= 0) return;

        let credentials;
        try {
            credentials = await fetchWebSocketCredentials(serverId);
        } catch (error) {
            if (mountedRef.current) scheduleReconnect({ type: 'credentials_error', error });
            return;
        }

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
                        retry.markConnected();
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
                                    uptime: (stats.uptime as number) ?? 0,
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

        ws.onclose = (event: CloseEvent) => {
            setIsConnected(false);
            wsRef.current = null;
            if (mountedRef.current) scheduleReconnect({ type: 'close', code: event.code });
        };

        ws.onerror = () => {
            ws.close();
        };
    // eslint-disable-next-line react-hooks/exhaustive-deps
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

    return { resources, serverState, isConnected, isGaveUp };
}
