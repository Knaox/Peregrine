import { useState, useRef, useCallback, useEffect } from 'react';
import type { ConsoleMessage, WebSocketEvent } from '@/types/ConsoleMessage';
import { fetchWebSocketCredentials } from '@/services/serverApi';

const MAX_MESSAGES = 1000;
const ANSI_REGEX = /\x1b\[[0-9;]*[a-zA-Z]/g;
const MAX_BACKOFF = 30_000;

export function useConsoleWebSocket(serverId: number) {
    const [messages, setMessages] = useState<ConsoleMessage[]>([]);
    const [isConnected, setIsConnected] = useState(false);
    const [serverState, setServerState] = useState<string | null>(null);

    const wsRef = useRef<WebSocket | null>(null);
    const reconnectTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const backoffRef = useRef(1000);
    const messageIdRef = useRef(0);
    const mountedRef = useRef(true);

    const clearMessages = useCallback(() => {
        setMessages([]);
    }, []);

    const sendWsCommand = useCallback((command: string) => {
        const ws = wsRef.current;
        if (!ws || ws.readyState !== WebSocket.OPEN) return;

        const payload: WebSocketEvent = {
            event: 'send command',
            args: [command],
        };
        ws.send(JSON.stringify(payload));
    }, []);

    const addMessage = useCallback((text: string) => {
        const cleaned = text.replace(ANSI_REGEX, '');
        const id = ++messageIdRef.current;
        const msg: ConsoleMessage = { id, text: cleaned, timestamp: Date.now() };

        setMessages((prev) => {
            const next = [...prev, msg];
            if (next.length > MAX_MESSAGES) {
                return next.slice(next.length - MAX_MESSAGES);
            }
            return next;
        });
    }, []);

    const connect = useCallback(async () => {
        if (!mountedRef.current) return;

        try {
            const credentials = await fetchWebSocketCredentials(serverId);
            if (!mountedRef.current) return;

            const ws = new WebSocket(credentials.socket);
            wsRef.current = ws;

            ws.onopen = () => {
                const authPayload: WebSocketEvent = {
                    event: 'auth',
                    args: [credentials.token],
                };
                ws.send(JSON.stringify(authPayload));
            };

            ws.onmessage = (event: MessageEvent) => {
                if (!mountedRef.current) return;

                let parsed: WebSocketEvent;
                try {
                    parsed = JSON.parse(event.data as string) as WebSocketEvent;
                } catch {
                    return;
                }

                switch (parsed.event) {
                    case 'auth success': {
                        setIsConnected(true);
                        backoffRef.current = 1000;
                        const logsPayload: WebSocketEvent = {
                            event: 'send logs',
                            args: [],
                        };
                        ws.send(JSON.stringify(logsPayload));
                        break;
                    }
                    case 'console output': {
                        for (const line of parsed.args) {
                            addMessage(line);
                        }
                        break;
                    }
                    case 'status': {
                        if (parsed.args[0]) {
                            setServerState(parsed.args[0]);
                        }
                        break;
                    }
                    case 'token expiring': {
                        fetchWebSocketCredentials(serverId)
                            .then((creds) => {
                                if (ws.readyState === WebSocket.OPEN) {
                                    const reAuthPayload: WebSocketEvent = {
                                        event: 'auth',
                                        args: [creds.token],
                                    };
                                    ws.send(JSON.stringify(reAuthPayload));
                                }
                            })
                            .catch(() => {});
                        break;
                    }
                    case 'token expired': {
                        ws.close();
                        break;
                    }
                }
            };

            ws.onclose = () => {
                if (!mountedRef.current) return;
                setIsConnected(false);
                scheduleReconnect();
            };

            ws.onerror = () => {
                if (!mountedRef.current) return;
                setIsConnected(false);
            };
        } catch {
            if (!mountedRef.current) return;
            setIsConnected(false);
            scheduleReconnect();
        }
    }, [serverId, addMessage]);

    const scheduleReconnect = useCallback(() => {
        if (reconnectTimerRef.current) {
            clearTimeout(reconnectTimerRef.current);
        }
        const delay = backoffRef.current;
        backoffRef.current = Math.min(delay * 2, MAX_BACKOFF);
        reconnectTimerRef.current = setTimeout(() => {
            connect();
        }, delay);
    }, [connect]);

    useEffect(() => {
        mountedRef.current = true;
        connect();

        return () => {
            mountedRef.current = false;
            if (reconnectTimerRef.current) {
                clearTimeout(reconnectTimerRef.current);
                reconnectTimerRef.current = null;
            }
            if (wsRef.current) {
                wsRef.current.close();
                wsRef.current = null;
            }
        };
    }, [connect]);

    return { messages, isConnected, serverState, sendWsCommand, clearMessages };
}
