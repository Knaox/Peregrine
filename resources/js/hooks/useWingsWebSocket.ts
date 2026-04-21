import { useState, useRef, useCallback, useEffect } from 'react';
import { fetchWebSocketCredentials } from '@/services/serverApi';
import { useWsRetryState, type WsFailure } from '@/hooks/useWsRetryState';
import type { ServerResources } from '@/types/ServerResources';
import type { ConsoleMessage } from '@/types/ConsoleMessage';

const MAX_MESSAGES = 1000;
const ANSI_REGEX = /\x1b\[[0-9;]*[a-zA-Z]/g;
const KEEPALIVE_INTERVAL = 10_000;

interface WsEvent {
    event: string;
    args: string[];
}

interface UseWingsWebSocketOptions {
    /** Subscribe to console output events */
    console?: boolean;
    /** Subscribe to stats events */
    stats?: boolean;
}

interface UseWingsWebSocketReturn {
    messages: ConsoleMessage[];
    resources: ServerResources | undefined;
    serverState: string;
    isConnected: boolean;
    /** True once the retry policy has given up (rate-limited or permission-denied). */
    isGaveUp: boolean;
    sendCommand: (command: string) => void;
    clearMessages: () => void;
}

export function useWingsWebSocket(
    serverId: number,
    options: UseWingsWebSocketOptions = { stats: true },
): UseWingsWebSocketReturn {
    const [messages, setMessages] = useState<ConsoleMessage[]>([]);
    const [resources, setResources] = useState<ServerResources | undefined>(undefined);
    const [serverState, setServerState] = useState<string>('offline');
    const [isConnected, setIsConnected] = useState(false);
    const [isGaveUp, setIsGaveUp] = useState(false);

    const wsRef = useRef<WebSocket | null>(null);
    const reconnectTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const keepaliveTimer = useRef<ReturnType<typeof setInterval> | null>(null);
    const msgId = useRef(0);
    const alive = useRef(false);
    const retry = useWsRetryState();

    const clearMessages = useCallback(() => setMessages([]), []);

    const sendCommand = useCallback((command: string) => {
        const ws = wsRef.current;
        if (ws?.readyState === WebSocket.OPEN) {
            ws.send(JSON.stringify({ event: 'send command', args: [command] }));
        }
    }, []);

    const cleanup = useCallback(() => {
        if (reconnectTimer.current) {
            clearTimeout(reconnectTimer.current);
            reconnectTimer.current = null;
        }
        if (keepaliveTimer.current) {
            clearInterval(keepaliveTimer.current);
            keepaliveTimer.current = null;
        }
        const ws = wsRef.current;
        if (ws) {
            ws.onopen = null;
            ws.onmessage = null;
            ws.onclose = null;
            ws.onerror = null;
            if (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING) {
                ws.close();
            }
            wsRef.current = null;
        }
    }, []);

    const scheduleReconnect = useCallback((signal: WsFailure) => {
        if (!alive.current) return;
        if (retry.shouldGiveUp(signal)) {
            setIsGaveUp(true);
            return;
        }
        const delay = retry.nextDelay();
        reconnectTimer.current = setTimeout(() => {
            void connect();
        }, delay);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const startKeepalive = useCallback((ws: WebSocket) => {
        if (keepaliveTimer.current) clearInterval(keepaliveTimer.current);
        keepaliveTimer.current = setInterval(() => {
            if (ws.readyState === WebSocket.OPEN) {
                ws.send(JSON.stringify({ event: 'send stats', args: [] }));
            }
        }, KEEPALIVE_INTERVAL);
    }, []);

    const connect = useCallback(async () => {
        if (!alive.current || serverId <= 0) return;
        cleanup();

        let credentials;
        try {
            credentials = await fetchWebSocketCredentials(serverId);
        } catch (error) {
            if (alive.current) scheduleReconnect({ type: 'credentials_error', error });
            return;
        }

        if (!alive.current) return;

        const ws = new WebSocket(credentials.socket);
        wsRef.current = ws;

        ws.onopen = () => {
            ws.send(JSON.stringify({ event: 'auth', args: [credentials.token] }));
        };

        ws.onmessage = (evt: MessageEvent) => {
            if (!alive.current) return;
            let data: WsEvent;
            try {
                data = JSON.parse(evt.data as string) as WsEvent;
            } catch {
                return;
            }

            switch (data.event) {
                case 'auth success':
                    setIsConnected(true);
                    retry.markConnected();
                    startKeepalive(ws);
                    // Request initial data
                    ws.send(JSON.stringify({ event: 'send stats', args: [] }));
                    if (options.console) {
                        ws.send(JSON.stringify({ event: 'send logs', args: [] }));
                    }
                    break;

                case 'console output':
                    if (options.console && data.args[0] !== undefined) {
                        const cleaned = data.args[0].replace(ANSI_REGEX, '');
                        const id = ++msgId.current;
                        setMessages((prev) => {
                            const next = [...prev, { id, text: cleaned, timestamp: Date.now() }];
                            return next.length > MAX_MESSAGES ? next.slice(-MAX_MESSAGES) : next;
                        });
                    }
                    break;

                case 'status':
                    if (data.args[0]) setServerState(data.args[0]);
                    break;

                case 'stats':
                    if (data.args[0]) {
                        try {
                            const s = JSON.parse(data.args[0]) as Record<string, unknown>;
                            const net = s.network as Record<string, number> | undefined;
                            setResources({
                                state: (s.state as string) ?? 'offline',
                                cpu: (s.cpu_absolute as number) ?? 0,
                                memory_bytes: (s.memory_bytes as number) ?? 0,
                                disk_bytes: (s.disk_bytes as number) ?? 0,
                                network_rx: net?.rx_bytes ?? 0,
                                network_tx: net?.tx_bytes ?? 0,
                                uptime: (s.uptime as number) ?? 0,
                            });
                            if (s.state) setServerState(s.state as string);
                        } catch { /* ignore */ }
                    }
                    break;

                case 'token expiring':
                    fetchWebSocketCredentials(serverId)
                        .then((creds) => {
                            if (ws.readyState === WebSocket.OPEN) {
                                ws.send(JSON.stringify({ event: 'auth', args: [creds.token] }));
                            }
                        })
                        .catch(() => { /* will get token expired next */ });
                    break;

                case 'token expired':
                case 'jwt error':
                    ws.close();
                    break;

                case 'daemon error':
                    if (options.console && data.args[0]) {
                        const id = ++msgId.current;
                        setMessages((prev) => {
                            const next = [...prev, { id, text: `[ERROR] ${data.args[0]}`, timestamp: Date.now() }];
                            return next.length > MAX_MESSAGES ? next.slice(-MAX_MESSAGES) : next;
                        });
                    }
                    break;
            }
        };

        ws.onclose = (event: CloseEvent) => {
            setIsConnected(false);
            wsRef.current = null;
            if (keepaliveTimer.current) {
                clearInterval(keepaliveTimer.current);
                keepaliveTimer.current = null;
            }
            if (alive.current) scheduleReconnect({ type: 'close', code: event.code });
        };

        ws.onerror = () => {
            // onclose will fire after onerror
        };
    }, [serverId, options.console, options.stats, cleanup, scheduleReconnect, startKeepalive]);

    useEffect(() => {
        alive.current = true;
        // Small delay to survive React StrictMode's mount/unmount/remount cycle in dev
        const initTimer = setTimeout(() => {
            if (alive.current) void connect();
        }, 100);
        return () => {
            alive.current = false;
            clearTimeout(initTimer);
            cleanup();
        };
    }, [connect, cleanup]);

    return { messages, resources, serverState, isConnected, isGaveUp, sendCommand, clearMessages };
}
